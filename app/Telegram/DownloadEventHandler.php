<?php

namespace App\Telegram;

use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\SimpleEventHandler;
use App\Models\TelegramDownloadJob;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadEventHandler extends SimpleEventHandler
{
    public const STARTUP_LOGGER = true;

    /**
     * Получить список администраторов для отчётов об ошибках.
     */
    public function getReportPeers()
    {
        return ['@selionchik']; // Замените на свой username
    }

    /**
     * Эта функция будет выполняться каждые 5 секунд.
     */
    #[Cron(period: 5.0)]
    public function checkDownloadQueue(): void
    {
        // Берём первую невыполненную задачу
        $job = TelegramDownloadJob::where('status', 'pending')->first();

        if (!$job) {
            return;
        }

        Log::info("📦 EventHandler: Начинаем обработку задачи #{$job->id}");

        // Отмечаем, что задача в процессе
        $job->update(['status' => 'processing']);

        try {
            // Получаем сообщение, которое нужно скачать
            $message = TelegramMessage::find($job->message_id);

            if (!$message || !$message->media_info) {
                throw new \Exception("Сообщение или медиа не найдено");
            }

            $mediaInfo = json_decode($message->media_info, true);
            $fileInfo = $this->extractFileInfo($mediaInfo);
            
            if (!$fileInfo) {
                throw new \Exception("Не удалось извлечь информацию о файле");
            }

            // Формируем путь для сохранения
            $datePath = now()->format('Y/m/d');
            $fileName = $fileInfo['id'] . '_' . time() . '.' . $fileInfo['ext'];
            $relativePath = "telegram/downloads/{$job->account_name}/{$datePath}/{$fileName}";
            $fullPath = Storage::disk('public')->path($relativePath);

            // Создаём папку
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            // СКАЧИВАЕМ ФАЙЛ (здесь нет проблем с IPC!)
            $this->downloadToFile($fileInfo, $fullPath);

            // Обновляем запись о сообщении
            TelegramMessage::where('id', $job->message_id)->update([
                'downloaded_file' => $relativePath,
                'file_downloaded_at' => now(),
            ]);

            // Отмечаем задачу выполненной
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_path' => $relativePath,
            ]);

            Log::info("✅ EventHandler: Задача #{$job->id} выполнена");

        } catch (\Exception $e) {
            Log::error("❌ EventHandler: Ошибка выполнения задачи #{$job->id}: " . $e->getMessage());
            
            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'attempts' => $job->attempts + 1,
            ]);
        }
    }

    private function extractFileInfo(array $mediaInfo): ?array
    {
        if (isset($mediaInfo['photo'])) {
            return [
                'id' => $mediaInfo['photo']['id'],
                'ext' => 'jpg',
            ];
        }
        if (isset($mediaInfo['document'])) {
            $ext = 'bin';
            if (isset($mediaInfo['document']['mime_type'])) {
                if (str_contains($mediaInfo['document']['mime_type'], 'video')) $ext = 'mp4';
                elseif (str_contains($mediaInfo['document']['mime_type'], 'audio')) $ext = 'mp3';
            }
            return [
                'id' => $mediaInfo['document']['id'],
                'ext' => $ext,
            ];
        }
        return null;
    }
}