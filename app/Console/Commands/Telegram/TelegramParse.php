<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\MultiAccountService;
use App\Services\Telegram\TelegramParserService;
use App\Models\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:parse ' .
        '{--account= : Конкретный аккаунт} ' .
        '{--chat= : ID конкретного чата} ' .
        '{--timeout=240 : Максимальное время выполнения в секундах} ' .
        '{--limit=50 : Лимит сообщений на чат}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг всех чатов и сообщений с контролем времени';

    protected MultiAccountService $multiAccount;
    protected TelegramParserService $parser;
    
    protected float $startTime;
    protected int $processedChats = 0;
    protected int $processedMessages = 0;

    /**
     * Create a new command instance.
     */
    public function __construct(
        MultiAccountService $multiAccount,
        TelegramParserService $parser
    ) {
        parent::__construct();
        $this->multiAccount = $multiAccount;
        $this->parser = $parser;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->startTime = microtime(true);
        $timeout = (int)$this->option('timeout');
        
        $this->info('Начинаем парсинг Telegram (таймаут: ' . $timeout . ' сек)...');

        // Проверяем, есть ли сохранённый прогресс
        $resumeFrom = Cache::get('telegram_parse_resume', null);
        if ($resumeFrom) {
            $this->warn("Возобновляем парсинг с чата ID: {$resumeFrom}");
        }

        $accounts = $this->getAccounts();

        if (empty($accounts)) {
            $this->error('Нет доступных аккаунтов');
            return 1;
        }

        foreach ($accounts as $account) {
            if ($this->isTimeExpired($timeout)) {
                $this->warn('Превышено время выполнения, сохраняем прогресс');
                return 0;
            }

            $this->newLine();
            $this->line("Обработка аккаунта: {$account->name}");

            try {
                $dialogs = $this->parser->fetchDialogs($account->name);
                
                if (empty($dialogs)) {
                    $this->warn("  Нет диалогов");
                    continue;
                }

                $this->line("  Найдено диалогов: " . count($dialogs));
                
                foreach ($dialogs as $chat) {
                    if ($this->isTimeExpired($timeout)) {
                        Cache::put('telegram_parse_resume', $chat->id, 3600);
                        $this->warn("Превышено время выполнения, сохраняем прогресс на чате {$chat->title}");
                        return 0;
                    }

                    if ($this->option('chat') && $chat->id != $this->option('chat')) {
                        continue;
                    }

                    if ($resumeFrom && $chat->id != $resumeFrom) {
                        continue;
                    }
                    if ($resumeFrom && $chat->id == $resumeFrom) {
                        $resumeFrom = null;
                    }

                    if (!$account->canParse()) {
                        $this->warn("\n  Аккаунт достиг лимита сообщений");
                        break;
                    }

                    $this->line("  Чат {$chat->title}: получение сообщений...");
                    
                    $messages = $this->parser->fetchMessages(
                        $chat, 
                        (int)$this->option('limit')
                    );

                    $this->processedMessages += count($messages);
                    $this->processedChats++;

                    $this->line("    +" . count($messages) . " сообщений");

                    usleep(rand(500000, 2000000));
                }

                Cache::forget('telegram_parse_resume');

            } catch (\Exception $e) {
                $this->error("  Ошибка: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Готово! Обработано чатов: {$this->processedChats}, сообщений: {$this->processedMessages}");
        $this->line("Время выполнения: " . round(microtime(true) - $this->startTime, 2) . " сек");

        return 0;
    }

    protected function isTimeExpired(int $timeout): bool
    {
        return (microtime(true) - $this->startTime) > $timeout;
    }

    protected function getAccounts(): array
    {
        if ($accountName = $this->option('account')) {
            $account = TelegramAccount::where('name', $accountName)->first();
            return $account ? [$account] : [];
        }

        return TelegramAccount::where('status', 'connected')->get()->all();
    }
}