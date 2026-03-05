<?php

namespace App\Console\Commands\Telegram;

use App\Models\TelegramAccount;
use Illuminate\Console\Command;

class TelegramResetLimits extends Command
{
    protected $signature = 'telegram:reset-limits';

    protected $description = 'Сброс дневных лимитов сообщений для всех аккаунтов';

    public function handle()
    {
        $this->info('Сброс лимитов...');

        $updated = TelegramAccount::query()->update(['messages_parsed_today' => 0]);

        $this->info("Сброшено лимитов для {$updated} аккаунтов");

        return 0;
    }
}