<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\MultiAccountService;
use Illuminate\Console\Command;

class TelegramTestDialogs extends Command
{
    protected $signature = 'telegram:test-dialogs {account}';

    protected $description = 'Тест получения диалогов';

    protected MultiAccountService $multiAccount;

    public function __construct(MultiAccountService $multiAccount)
    {
        parent::__construct();
        $this->multiAccount = $multiAccount;
    }

    public function handle()
    {
        $accountName = $this->argument('account');
        
        $this->info("Тест получения диалогов для аккаунта: {$accountName}");

        $madeline = $this->multiAccount->getAccount($accountName);
        
        if (!$madeline) {
            $this->error("Аккаунт недоступен");
            return 1;
        }

        try {
            // Пробуем разные методы получения диалогов
            $methods = [
                'getDialogs' => $madeline->getDialogs(),
                'getChats' => $madeline->getChats(),
                'getFullDialogs' => $madeline->getFullDialogs(),
            ];

            foreach ($methods as $method => $result) {
                $this->line("{$method}: " . count($result) . " результатов");
                
                if (!empty($result)) {
                    $this->info("✓ {$method} работает!");
                    
                    // Покажем первые 3
                    $this->line("Примеры:");
                    foreach (array_slice($result, 0, 3) as $item) {
                        if (isset($item['peer'])) {
                            $this->line("  - " . json_encode($item['peer']));
                        } elseif (isset($item['title'])) {
                            $this->line("  - " . $item['title']);
                        }
                    }
                    break;
                }
            }

            // Если ничего не найдено, попробуем явно обновить состояние
            if (empty($methods['getDialogs']) && empty($methods['getChats']) && empty($methods['getFullDialogs'])) {
                $this->warn("Ничего не найдено, пробуем обновить состояние...");
                
                $madeline->getSelf(); // Просто чтобы "разбудить"
                sleep(2);
                
                $dialogs = $madeline->getDialogs();
                $this->info("После обновления: " . count($dialogs) . " диалогов");
            }

        } catch (\Exception $e) {
            $this->error("Ошибка: " . $e->getMessage());
        }

        return 0;
    }
}