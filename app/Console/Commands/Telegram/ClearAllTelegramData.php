<?php

namespace App\Console\Commands\Telegram;

use App\Models\TelegramMessage;
use App\Models\TelegramChat;
use App\Models\TelegramUserComment;
use App\Models\TelegramProxy;
use App\Models\TelegramAccount;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearAllTelegramData extends Command
{
    protected $signature = 'telegram:clear-all {--force : Без подтверждения}';
    protected $description = 'ПОЛНОСТЬЮ очищает все данные Telegram из БД';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  ВНИМАНИЕ! Эта команда УДАЛИТ ВСЕ данные Telegram:');
            $this->line('   - Все чаты');
            $this->line('   - Все сообщения');
            $this->line('   - Все комментарии');
            $this->line('   - Все прокси');
            $this->line('   - Все заказы');
            $this->line('   - Все скачанные файлы');
            
            if (!$this->confirm('Вы уверены? [y/N]')) {
                $this->info('Отменено');
                return 0;
            }
        }

        $this->info('🧹 Начинаем полную очистку...');

        // 1. Удаляем файлы (физически)
        $this->cleanupFiles();

        // 2. Очищаем таблицы
        $this->truncateTables();

        $this->info('✅ База данных Telegram полностью очищена!');
        
        return 0;
    }

    protected function cleanupFiles()
    {
        $this->line('Удаление скачанных файлов...');
        
        $paths = [
            storage_path('app/public/telegram'),
            public_path('storage/telegram'),
            storage_path('app/public/telegram/downloads'),
        ];

        $deleted = 0;
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob("{$path}/*/*/*/*.jpg");
                $files = array_merge($files, glob("{$path}/*/*/*/*.mp4"));
                $files = array_merge($files, glob("{$path}/*/*/*/*.bin"));
                
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
                
                // Удаляем пустые папки
                $this->rmdir_recursive($path);
            }
        }

        $this->line("   Удалено файлов: {$deleted}");
    }

    protected function rmdir_recursive($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->rmdir_recursive($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function truncateTables()
    {
        // Отключаем проверку внешних ключей
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'telegram_messages',
            'telegram_user_comments',
            'telegram_posts',
            'telegram_chats',
            'telegram_proxies',
            'telegram_flood_logs',
            'orders',
        ];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            DB::table($table)->truncate();
            $this->line("   Очищено {$table}: {$count} записей");
        }

        // Не трогаем telegram_accounts — там хранятся настройки аккаунтов
        
        // Включаем обратно
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}