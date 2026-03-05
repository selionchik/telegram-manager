<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\MultiAccountService;
use Illuminate\Console\Command;

class TelegramDebugDialogs extends Command
{
    protected $signature = 'telegram:debug-dialogs {account}';

    protected $description = 'Отладка получения диалогов';

    protected MultiAccountService $multiAccount;

    public function __construct(MultiAccountService $multiAccount)
    {
        parent::__construct();
        $this->multiAccount = $multiAccount;
    }

    public function handle()
    {
        $accountName = $this->argument('account');
        
        $this->info("Отладка получения диалогов для аккаунта: {$accountName}");

        $madeline = $this->multiAccount->getAccount($accountName);
        
        if (!$madeline) {
            $this->error("Аккаунт недоступен");
            return 1;
        }

        try {
            // Пробуем разные методы
            $methods = [
                'messages->getDialogs()' => function() use ($madeline) {
                    return $madeline->messages->getDialogs(limit: 10);
                },
                'messages->getChats()' => function() use ($madeline) {
                    return $madeline->messages->getChats(id: []);
                },
                'channels->getChannels()' => function() use ($madeline) {
                    return $madeline->channels->getChannels(id: []);
                },
                'getFullDialogs()' => function() use ($madeline) {
                    return $madeline->getFullDialogs();
                },
            ];

            foreach ($methods as $name => $callback) {
                $this->newLine();
                $this->line("Пробуем: {$name}");
                
                try {
                    $result = $callback();
                    
                    $this->line("  Тип результата: " . gettype($result));
                    
                    if (is_array($result)) {
                        $this->line("  Ключи: " . json_encode(array_keys($result)));
                        
                        // Ищем где могут быть диалоги
                        if (isset($result['dialogs'])) {
                            $this->info("  ✓ Найдены диалоги в ['dialogs']: " . count($result['dialogs']));
                            $this->line("  Пример первого диалога: " . json_encode(array_slice($result['dialogs'][0] ?? [], 0, 3)));
                        } elseif (isset($result['chats'])) {
                            $this->info("  ✓ Найдены чаты в ['chats']: " . count($result['chats']));
                        } elseif (isset($result['users'])) {
                            $this->info("  ✓ Найдены пользователи: " . count($result['users']));
                        } else {
                            $this->line("  Структура: " . substr(json_encode($result), 0, 200));
                        }
                    }
                    
                } catch (\Exception $e) {
                    $this->error("  Ошибка: " . $e->getMessage());
                }
            }

            // Попробуем получить информацию о себе
            $this->newLine();
            $this->line("Информация о пользователе:");
            $me = $madeline->getSelf();
            $this->info("ID: " . ($me['id'] ?? 'неизвестно'));
            $this->info("Имя: " . ($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));

        } catch (\Exception $e) {
            $this->error("Общая ошибка: " . $e->getMessage());
        }

        return 0;
    }
}