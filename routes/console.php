<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// TELEGRAM COMMANDS
// ============================================

/**
 * Авторизация Telegram аккаунта
 * 
 * Примеры:
 *   php artisan telegram:auth account1
 *   php artisan telegram:auth account1 --code=12345
 *   php artisan telegram:auth account1 --code=12345 --password=secret
 */
Artisan::command('telegram:auth {account} {--code=} {--password=}', function () {
    $account = $this->argument('account');
    $code = $this->option('code');
    $password = $this->option('password');
    
    $this->call(\App\Console\Commands\Telegram\TelegramAuth::class, [
        'account' => $account,
        '--code' => $code,
        '--password' => $password,
    ]);
})->purpose('Авторизация Telegram аккаунта');

/**
 * Парсинг всех чатов и сообщений
 * 
 * Примеры:
 *   php artisan telegram:parse
 *   php artisan telegram:parse --account=account1
 *   php artisan telegram:parse --chat=123456789
 *   php artisan telegram:parse --limit=100
 */
// Artisan::command('telegram:parse {--account=} {--chat=} {--limit=50}', function () {
//     $this->call(\App\Console\Commands\Telegram\TelegramParse::class, [
//         '--account' => $this->option('account'),
//         '--chat' => $this->option('chat'),
//         '--limit' => $this->option('limit'),
//     ]);
// })->purpose('Парсинг всех чатов и сообщений');

/**
 * Поиск заказов в новых сообщениях
 * 
 * Примеры:
 *   php artisan telegram:orders
 *   php artisan telegram:orders --mark-read
 *   php artisan telegram:orders --limit=200
 */
Artisan::command('telegram:orders {--mark-read} {--limit=100}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramCheckOrders::class, [
        '--mark-read' => $this->option('mark-read'),
        '--limit' => $this->option('limit'),
    ]);
})->purpose('Поиск заказов в новых сообщениях');

/**
 * Сброс дневных лимитов сообщений
 * 
 * Пример:
 *   php artisan telegram:reset-limits
 */
Artisan::command('telegram:reset-limits', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramResetLimits::class);
})->purpose('Сброс дневных лимитов сообщений для всех аккаунтов');

/**
 * Тест подключения к Telegram
 * 
 * Пример:
 *   php artisan telegram:test account1
 */
Artisan::command('telegram:test {account}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramTest::class, [
        'account' => $this->argument('account'),
    ]);
})->purpose('Тест подключения к Telegram');

/**
 * Исключить чат из парсинга
 * 
 * Пример:
 *   php artisan telegram:exclude 123456789 "Спам"
 */
Artisan::command('telegram:exclude {chat_id} {reason?}', function () {
    $chatId = $this->argument('chat_id');
    $reason = $this->argument('reason');
    
    try {
        $service = app(\App\Services\Telegram\ChatExclusionService::class);
        $service->excludeChat($chatId, $reason);
        $this->info("Чат {$chatId} исключён из парсинга");
    } catch (\Exception $e) {
        $this->error("Ошибка: " . $e->getMessage());
    }
})->purpose('Исключить чат из парсинга');

/**
 * Вернуть чат в парсинг
 * 
 * Пример:
 *   php artisan telegram:include 123456789
 */
Artisan::command('telegram:include {chat_id}', function () {
    $chatId = $this->argument('chat_id');
    
    try {
        $service = app(\App\Services\Telegram\ChatExclusionService::class);
        $service->includeChat($chatId);
        $this->info("Чат {$chatId} возвращён в парсинг");
    } catch (\Exception $e) {
        $this->error("Ошибка: " . $e->getMessage());
    }
})->purpose('Вернуть чат в парсинг');

/**
 * Показать статистику
 * 
 * Пример:
 *   php artisan telegram:stats
 */
Artisan::command('telegram:stats', function () {
    $accounts = \App\Models\TelegramAccount::all();
    $chats = \App\Models\TelegramChat::count();
    $excluded = \App\Models\TelegramChat::excluded()->count();
    $messages = \App\Models\TelegramMessage::count();
    $orders = \App\Models\Order::count();
    
    $this->info('=== Telegram Статистика ===');
    $this->line("Аккаунтов: " . $accounts->count());
    
    foreach ($accounts as $account) {
        $this->line("  - {$account->name}: {$account->status}, сегодня: {$account->messages_parsed_today} сообщений");
    }
    
    $this->line("Чатов всего: {$chats}");
    $this->line("Исключено чатов: {$excluded}");
    $this->line("Сообщений в БД: {$messages}");
    $this->line("Заказов найдено: {$orders}");
    
    $newOrders = \App\Models\Order::where('status', 'new')->count();
    if ($newOrders > 0) {
        $this->warn("Новых заказов: {$newOrders}");
    }
})->purpose('Показать статистику');