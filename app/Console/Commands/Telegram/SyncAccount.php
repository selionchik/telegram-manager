<?php

namespace App\Console\Commands\Telegram;

use App\Models\TelegramAccount;
use App\Services\Telegram\GatewayService;
use Illuminate\Console\Command;

class SyncAccount extends Command
{
    protected $signature = 'telegram:sync-account {name=account1}';
    protected $description = 'Синхронизация информации об аккаунте с Gateway';

    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        parent::__construct();
        $this->gateway = $gateway;
    }

    public function handle()
    {
        $name = $this->argument('name');
        $this->info("🔄 Синхронизация аккаунта {$name}...");

        $result = $this->gateway->getMe();

        if (($result['status'] ?? '') !== 'ok') {
            $this->error('❌ Не удалось получить информацию об аккаунте');
            return 1;
        }

        $account = $result['account'];

        $tgAccount = TelegramAccount::updateOrCreate(
            ['name' => $name],
            [
                'tg_id' => $account['id'],
                'first_name' => $account['first_name'],
                'last_name' => $account['last_name'],
                'username' => $account['username'],
                'phone' => $account['phone'],
                'status' => 'connected',
            ]
        );

        $this->info('✅ Аккаунт синхронизирован:');
        $this->line("   ID: {$account['id']}");
        $this->line("   Имя: {$account['first_name']} {$account['last_name']}");
        $this->line("   Username: @{$account['username']}");
        $this->line("   Телефон: {$account['phone']}");

        return 0;
    }
}