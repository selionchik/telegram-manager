<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\Telegram\TelegramAuth::class,
        Commands\Telegram\TelegramCheckOrders::class,
        Commands\Telegram\TelegramResetLimits::class,
        Commands\Telegram\TelegramTest::class,
        Commands\Telegram\TelegramExclude::class,
        Commands\Telegram\TelegramInclude::class,
        Commands\Telegram\CollectProxies::class,
        Commands\Telegram\SyncTelegram::class,
        Commands\Telegram\TelegramParse::class,
        Commands\Telegram\CheckProxies::class,
        Commands\Telegram\CleanupFiles::class,        
    ];

protected function schedule(Schedule $schedule)
{
        // Синхронизация чатов каждый час
        $schedule->command('telegram:sync')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    
    // // Воркер для очередей
    // $schedule->command('queue:work --queue=downloads --max-time=60 --stop-when-empty')
    //     ->everyMinute()
    //     ->withoutOverlapping()
    //     ->runInBackground();
    
    // Очистка старых файлов раз в день
    $schedule->command('telegram:cleanup-files --days=30')
        ->daily();        


    // Остальные задачи...
    $schedule->command('telegram:collect-proxies')->hourly();
    $schedule->command('telegram:check-proxies --all')->everyFifteenMinutes();
}
}