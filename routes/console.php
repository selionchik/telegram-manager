<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// TELEGRAM COMMANDS
// ============================================

/**
 * Авторизация Telegram аккаунта
 */
Artisan::command('telegram:auth {account} {--code=} {--password=} {--qr}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramAuth::class, [
        'account' => $this->argument('account'),
        '--code' => $this->option('code'),
        '--password' => $this->option('password'),
        '--qr' => $this->option('qr'),
    ]);
})->purpose('Авторизация Telegram аккаунта');

/**
 * Синхронизация чатов и сообщений (исправлено - добавлен --force)
 */
Artisan::command('telegram:sync {--chat=} {--force}', function () {
    $this->call(\App\Console\Commands\Telegram\SyncTelegram::class, [
        '--chat' => $this->option('chat'),
        '--force' => $this->option('force'),
    ]);
})->purpose('Синхронизация чатов и сообщений с Gateway');

/**
 * Парсинг всех чатов и сообщений
 */
Artisan::command('telegram:parse {--account=} {--chat=} {--limit=50} {--timeout=240}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramParse::class, [
        '--account' => $this->option('account'),
        '--chat' => $this->option('chat'),
        '--limit' => $this->option('limit'),
        '--timeout' => $this->option('timeout'),
    ]);
})->purpose('Парсинг всех чатов и сообщений');

/**
 * Поиск заказов в новых сообщениях
 */
Artisan::command('telegram:orders {--mark-read} {--limit=100}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramCheckOrders::class, [
        '--mark-read' => $this->option('mark-read'),
        '--limit' => $this->option('limit'),
    ]);
})->purpose('Поиск заказов в новых сообщениях');

/**
 * Сброс дневных лимитов сообщений
 */
Artisan::command('telegram:reset-limits', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramResetLimits::class);
})->purpose('Сброс дневных лимитов сообщений для всех аккаунтов');

/**
 * Тест подключения к Telegram
 */
Artisan::command('telegram:test {account}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramTest::class, [
        'account' => $this->argument('account'),
    ]);
})->purpose('Тест подключения к Telegram');

/**
 * Исключить чат из парсинга
 */
Artisan::command('telegram:exclude {chat_id} {reason?}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramExclude::class, [
        'chat_id' => $this->argument('chat_id'),
        'reason' => $this->argument('reason'),
    ]);
})->purpose('Исключить чат из парсинга');

/**
 * Вернуть чат в парсинг
 */
Artisan::command('telegram:include {chat_id}', function () {
    $this->call(\App\Console\Commands\Telegram\TelegramInclude::class, [
        'chat_id' => $this->argument('chat_id'),
    ]);
})->purpose('Вернуть чат в парсинг');

/**
 * Показать статистику
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