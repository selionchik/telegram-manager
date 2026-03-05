<?php

namespace App\Console\Commands\Telegram;

use App\Models\TelegramMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupFiles extends Command
{
    protected $signature = 'telegram:cleanup-files {--days=30 : Удалять файлы старше N дней}';
    protected $description = 'Очистка старых неиспользуемых файлов';

    public function handle()
    {
        $days = $this->option('days');
        $this->info("Очистка файлов старше {$days} дней...");

        $deleted = 0;
        $freed = 0;

        // Файлы, которые скачали но не открывали больше месяца
        $oldFiles = TelegramMessage::where('file_downloaded_at', '<', now()->subDays($days))
            ->whereNotNull('downloaded_file')
            ->get();

        foreach ($oldFiles as $message) {
            if ($message->downloaded_file && Storage::disk('public')->exists($message->downloaded_file)) {
                $size = Storage::disk('public')->size($message->downloaded_file);
                Storage::disk('public')->delete($message->downloaded_file);
                
                $message->update([
                    'downloaded_file' => null,
                    'file_downloaded_at' => null,
                ]);
                
                $deleted++;
                $freed += $size;
            }
        }

        $this->info("Удалено файлов: {$deleted}");
        $this->info("Освобождено: " . round($freed / 1048576, 2) . " MB");

        return 0;
    }
}