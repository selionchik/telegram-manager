<?php

namespace App\Console\Commands\Telegram;

use App\Http\Controllers\Telegram\ChatController;
use App\Models\TelegramChat;
use App\Models\TelegramAccount;
use App\Services\Telegram\GatewayService;
use Illuminate\Console\Command;

class SyncTelegram extends Command
{
    protected $signature = 'telegram:sync 
                            {--chat= : ID конкретного чата}
                            {--force : Принудительная полная синхронизация (даже если база не пуста)}';

    protected $description = 'Синхронизация чатов и сообщений с Gateway';

    protected ChatController $chatController;
    protected GatewayService $gateway;

    public function __construct(ChatController $chatController, GatewayService $gateway)
    {
        parent::__construct();
        $this->chatController = $chatController;
        $this->gateway = $gateway;
    }

    public function handle()
    {
        $this->info('🚀 Начинаем синхронизацию...');

        // Проверяем наличие аккаунта
        $account = TelegramAccount::where('status', 'connected')->first();
        
        if (!$account) {
            $this->warn('📦 Аккаунт не найден. Пытаемся получить из Gateway...');
            
            $me = $this->gateway->getMe();
            
            if (($me['status'] ?? '') === 'ok') {
                $account = TelegramAccount::create([
                    'name' => 'account1',
                    'tg_id' => $me['account']['id'],
                    'first_name' => $me['account']['first_name'],
                    'last_name' => $me['account']['last_name'] ?? '',
                    'username' => $me['account']['username'] ?? '',
                    'phone' => $me['account']['phone'] ?? '',
                    'status' => 'connected',
                ]);
                $this->info("✅ Аккаунт создан: {$account->first_name}");
            } else {
                $this->error('❌ Не удалось получить аккаунт из Gateway');
                return 1;
            }
        }

        if ($chatId = $this->option('chat')) {
            return $this->syncSingleChat($chatId);
        }

        return $this->syncAll();
    }

    protected function syncAll()
    {
        $chatsCount = TelegramChat::count();

        // ЭТАП 1: Синхронизация чатов
        $this->info("\n📋 ЭТАП 1: Синхронизация списка чатов");
        
        if ($chatsCount === 0 || $this->option('force')) {
            if ($this->option('force') && $chatsCount > 0) {
                $this->warn('⚠️ Принудительная перезагрузка всех чатов...');
            } else {
                $this->warn('📦 База чатов пуста. Выполняем первичную загрузку...');
            }
            
            $startTime = microtime(true);
            $saved = $this->chatController->syncDialogs();
            $time = round(microtime(true) - $startTime, 2);
            
            $this->info("✅ Сохранено чатов: {$saved} за {$time} сек");
        } else {
            $this->line('🔄 Обновление списка чатов...');
            $startTime = microtime(true);
            $saved = $this->chatController->syncDialogs();
            $time = round(microtime(true) - $startTime, 2);
            $this->info("✅ Обновлено чатов: {$saved} за {$time} сек");
        }

        $chats = TelegramChat::active()->get();
        
        if ($chats->isEmpty()) {
            $this->warn('⚠️ Нет активных чатов для синхронизации сообщений');
            return 0;
        }

        // ЭТАП 2: Синхронизация сообщений
        $this->info("\n📨 ЭТАП 2: Синхронизация сообщений для " . $chats->count() . " чатов");
        
        $bar = $this->output->createProgressBar($chats->count());
        $bar->setFormat("⏳ %current%/%max% [%bar%] %percent:3s%%\n  🕒 Текущий: %message%");
        $bar->start();

        $totalMessages = 0;
        $startTime = microtime(true);
        
        foreach ($chats as $index => $chat) {
            $bar->setMessage($chat->title);
            
            try {
                $count = $this->chatController->syncMessages($chat->id, 100);
                $totalMessages += $count;
                
                // Показываем прогресс в консоли
                $this->line("\n   ├─ [" . ($index + 1) . "/" . $chats->count() . "] +{$count} сообщений");
                
            } catch (\Exception $e) {
                $this->line("\n   ❌ Ошибка: " . $e->getMessage());
            }
            
            $bar->advance();
            usleep(500000);
        }

        $bar->finish();
        $time = round(microtime(true) - $startTime, 2);
        
        $this->newLine(2);
        $this->info("✅ Синхронизация завершена! Добавлено сообщений: {$totalMessages} за {$time} сек");

        return 0;
    }

    protected function syncSingleChat(int $chatId)
    {
        $this->line("🔄 Синхронизация чата {$chatId}...");
        
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            $this->warn("📦 Чат {$chatId} не найден в БД. Пытаемся загрузить...");
            
            $this->chatController->syncDialogs();
            
            $chat = TelegramChat::find($chatId);
            if (!$chat) {
                $this->error("❌ Чат {$chatId} не найден после загрузки");
                return 1;
            }
        }

        $count = $this->chatController->syncMessages($chatId, 200);
        $this->info("✅ Добавлено сообщений: {$count}");

        return 0;
    }
}
