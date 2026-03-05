<?php

namespace App\Jobs;

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Services\Telegram\MultiAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadTelegramFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Максимальное время выполнения (секунды)
    public $timeout = 120;
    
    // Количество попыток
    public $tries = 3;
    
    // Максимальное количество исключений до провала
    public $maxExceptions = 2;

    protected array $mediaInfo;
    protected TelegramChat $chat;
    protected ?TelegramMessage $message;

    /**
     * Create a new job instance.
     */
    public function __construct(array $mediaInfo, TelegramChat $chat, ?TelegramMessage $message = null)
    {
        $this->mediaInfo = $mediaInfo;
        $this->chat = $chat;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(MultiAccountService $multiAccount): void
    {
        Log::info("Начинаем скачивание файла для чата {$this->chat->id}");
        
        $startTime = microtime(true);
        
        try {
            $madeline = $multiAccount->getAccount($this->chat->account->name);
            
            if (!$madeline) {
                throw new \Exception("Аккаунт недоступен");
            }

            // Определяем тип медиа
            if (isset($this->mediaInfo['photo'])) {
                $fileInfo = $this->mediaInfo['photo'];
            } elseif (isset($this->mediaInfo['document'])) {
                $fileInfo = $this->mediaInfo['document'];
            } else {
                Log::warning("Неизвестный тип медиа");
                return;
            }

            $fileId = $fileInfo['id'] ?? null;
            if (!$fileId) {
                throw new \Exception("Нет ID файла");
            }

            // Формируем путь для сохранения
            $datePath = now()->format('Y/m/d');
            $fileName = $fileId . '_' . time() . '.jpg';
            $relativePath = "telegram/{$this->chat->account->name}/{$datePath}/{$fileName}";
            $fullPath = Storage::disk('public')->path($relativePath);

            // Создаём папку
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            // Скачиваем файл
            $madeline->downloadToFile($fileInfo, $fullPath);
            
            $downloadTime = round(microtime(true) - $startTime, 2);
            
            Log::info("Файл сохранён: {$relativePath}", [
                'size' => filesize($fullPath) ?? 0,
                'time' => $downloadTime . ' сек'
            ]);

            // Если есть сообщение, обновляем его
            if ($this->message) {
                $this->message->update([
                    'downloaded_file' => $relativePath
                ]);
            }

            // Если скачивание заняло много времени, логируем
            if ($downloadTime > 30) {
                Log::warning("Медленное скачивание файла", [
                    'file_id' => $fileId,
                    'time' => $downloadTime
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Ошибка скачивания файла: " . $e->getMessage());
            
            // Если это flood wait, ждём
            if (str_contains($e->getMessage(), 'FLOOD_WAIT')) {
                preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $matches);
                $wait = $matches[1] ?? 30;
                
                $this->release($wait);
                return;
            }
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job скачивания файла провалился после {$this->attempts()} попыток", [
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Определяем, когда повторить попытку
     */
    public function retryUntil()
    {
        return now()->addMinutes(10);
    }
}