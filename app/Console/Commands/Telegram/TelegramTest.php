<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\MultiAccountService;
use Illuminate\Console\Command;

class TelegramTest extends Command
{
    protected $signature = 'telegram:test {account}';

    protected $description = 'Тест подключения к Telegram';

    protected MultiAccountService $multiAccount;

    public function __construct(MultiAccountService $multiAccount)
    {
        parent::__construct();
        $this->multiAccount = $multiAccount;
    }

    public function handle()
    {
        $accountName = $this->argument('account');
        
        $this->info("Тест аккаунта: {$accountName}");

        try {
            $madeline = $this->multiAccount->getAccount($accountName);
            
            if (!$madeline) {
                $this->error('Аккаунт недоступен');
                return 1;
            }

            $this->line('Проверка авторизации...');
            $authorized = $madeline->isAuthorized();
            
            if ($authorized) {
                $this->info('✓ Аккаунт авторизован');
            } else {
                $this->error('✗ Аккаунт не авторизован');
                return 1;
            }

            $me = $madeline->getSelf();
            
            $this->info('✓ Подключение работает');
            $this->line("ID: " . ($me['id'] ?? 'неизвестно'));
            $this->line("Имя: " . ($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
            $this->line("Username: @" . ($me['username'] ?? 'отсутствует'));

            return 0;

        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return 1;
        }
    }
}