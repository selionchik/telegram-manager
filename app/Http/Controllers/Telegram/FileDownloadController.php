<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use App\Services\Telegram\MultiAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileDownloadController extends Controller
{
    protected MultiAccountService $multiAccount;

    public function __construct(MultiAccountService $multiAccount)
    {
        $this->multiAccount = $multiAccount;
    }

public function download(Request $request, int $messageId)
{
    Log::info("📥 Запрос на скачивание файла", ['message_id' => $messageId, 'time' => now()]);
    $startTime = microtime(true);
    
    try {
        $message = TelegramMessage::with('chat')->findOrFail($messageId);
        Log::info("✅ Сообщение найдено", ['chat_id' => $message->chat_id, 'has_media' => $message->has_media]);

        if (!$message->has_media) {
            Log::warning("❌ Нет медиа в сообщении");
            return response()->json(['error' => 'Нет медиа'], 404);
        }

        $message->update(['file_url_clicked' => true]);

        // Если уже скачан - возвращаем ссылку быстро
        if ($message->downloaded_file && Storage::disk('public')->exists($message->downloaded_file)) {
            Log::info("✅ Файл уже скачан", ['path' => $message->downloaded_file]);
            
            $url = Storage::url($message->downloaded_file);
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'cached' => true,
                'type' => $message->media_type
            ]);
        }

        // Получаем аккаунт через сервис
        Log::info("🔄 Получаем аккаунт MadelineProto");
        $madeline = $this->multiAccount->getAccount($message->chat->account->name);
        
        if (!$madeline) {
            Log::error("❌ Аккаунт недоступен");
            return response()->json(['error' => 'Аккаунт недоступен'], 500);
        }
try {
    // Получаем текущие настройки
    $currentSettings = $madeline->getSettings();
    
    // Создаем настройки IPC
    $ipcSettings = new \danog\MadelineProto\Settings\Ipc();
    // Устанавливаем медленный режим (это отключает IPC для веб-окружения)
    $ipcSettings->setSlow(true);
    
    // Обновляем настройки
    $currentSettings->setIpc($ipcSettings);
    $madeline->updateSettings($currentSettings);
    
    Log::info("✅ IPC отключен (режим slow)");
} catch (\Exception $e) {
    Log::warning("⚠️ Не удалось отключить IPC: " . $e->getMessage());
}        
        Log::info("✅ Аккаунт получен");

        // Декодируем media_info
        $mediaInfo = json_decode($message->media_info, true);
        Log::info("📦 Media info", ['type' => array_keys($mediaInfo)]);

        // Определяем тип медиа
        $fileInfo = null;
        $extension = 'bin';
        $mediaType = 'unknown';
        
        if (isset($mediaInfo['photo'])) {
            $fileInfo = $mediaInfo['photo'];
            $extension = 'jpg';
            $mediaType = 'photo';
            Log::info("🖼️ Тип: фото");
        } elseif (isset($mediaInfo['document'])) {
            $fileInfo = $mediaInfo['document'];
            $mediaType = 'document';
            Log::info("📄 Тип: документ");
            
            if (isset($fileInfo['mime_type'])) {
                if (str_contains($fileInfo['mime_type'], 'video')) {
                    $mediaType = 'video';
                    $extension = 'mp4';
                    Log::info("🎬 Определено как видео");
                } elseif (str_contains($fileInfo['mime_type'], 'audio')) {
                    $mediaType = 'audio';
                    $extension = 'mp3';
                    Log::info("🎵 Определено как аудио");
                }
            }
        } elseif (isset($mediaInfo['video'])) {
            $fileInfo = $mediaInfo['video'];
            $extension = 'mp4';
            $mediaType = 'video';
            Log::info("🎬 Тип: видео");
        } elseif (isset($mediaInfo['audio'])) {
            $fileInfo = $mediaInfo['audio'];
            $extension = 'mp3';
            $mediaType = 'audio';
            Log::info("🎵 Тип: аудио");
        }

        if (!$fileInfo) {
            Log::error("❌ Неподдерживаемый тип медиа", ['media_info' => $mediaInfo]);
            return response()->json(['error' => 'Неподдерживаемый тип'], 400);
        }

        $fileId = $fileInfo['id'] ?? null;
        if (!$fileId) {
            Log::error("❌ Нет ID файла");
            return response()->json(['error' => 'Нет ID файла'], 400);
        }
        Log::info("📋 ID файла", ['file_id' => substr($fileId, 0, 8) . '...']);

        // Формируем путь
        $datePath = now()->format('Y/m/d');
        $fileName = $fileId . '_' . time() . '.' . $extension;
        $relativePath = "telegram/downloads/{$message->chat->account->name}/{$datePath}/{$fileName}";
        $fullPath = Storage::disk('public')->path($relativePath);
        Log::info("📁 Путь для сохранения", ['path' => $relativePath]);

        // Создаём папку
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
            Log::info("📁 Создана папка");
        }

        // Устанавливаем таймаут
        set_time_limit(300);
        Log::info("⏱️ Начинаем скачивание через downloadToFile...");

        // ===== ВКЛЮЧАЕМ ЛОГИРОВАНИЕ MADELINEPROTO =====
        $logFile = storage_path("logs/madeline/debug_" . time() . ".log");
        $madeline->__call('setLogFile', [$logFile]);
        $madeline->__call('setLogLevel', [\danog\MadelineProto\Logger::ULTRA_VERBOSE]);
        Log::info("📝 Логи MadelineProto будут в: " . $logFile);
        // =============================================

        Log::info("🔥🔥🔥 ВЫЗОВ downloadToFile 🔥🔥🔥", [
            'file_id' => $fileId,
            'full_path' => $fullPath
        ]);

        try {
            $result = $madeline->downloadToFile($fileInfo, $fullPath);
            Log::info("✅✅✅ downloadToFile ВЕРНУЛ РЕЗУЛЬТАТ ✅✅✅", [
                'result' => json_encode($result)
            ]);
        } catch (\Exception $e) {
            Log::error("💥💥💥 downloadToFile ВЫБРОСИЛ ИСКЛЮЧЕНИЕ 💥💥💥", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        if (!file_exists($fullPath)) {
            throw new \Exception("Файл не был скачан");
        }

        $fileSize = filesize($fullPath);
        $downloadTime = round(microtime(true) - $startTime, 2);
        Log::info("✅ Файл успешно скачан", ['size' => $fileSize, 'time' => $downloadTime . ' сек']);

        // Обновляем запись
        $message->update([
            'downloaded_file' => $relativePath,
            'file_downloaded_at' => now(),
        ]);

        // Формируем URL
        $url = Storage::url($relativePath);

        return response()->json([
            'success' => true,
            'url' => $url,
            'type' => $mediaType,
            'download_time' => $downloadTime,
            'cached' => false
        ]);

    } catch (\Exception $e) {
        $totalTime = round(microtime(true) - $startTime, 2);
        Log::error("❌ Ошибка скачивания через {$totalTime} сек: " . $e->getMessage(), [
            'message_id' => $messageId,
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Ошибка скачивания: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Очистка старых файлов
     */
    public function cleanup(Request $request)
    {
        $days = $request->get('days', 30);

        // Находим файлы, которые не открывали
        $unusedFiles = TelegramMessage::where('file_downloaded_at', '<', now()->subDays($days))
            ->orWhere(function ($q) {
                $q->where('file_url_clicked', false)
                    ->whereNotNull('downloaded_file');
            })
            ->get();

        $deleted = 0;
        $freed   = 0;

        foreach ($unusedFiles as $message) {
            if ($message->downloaded_file && Storage::disk('public')->exists($message->downloaded_file)) {
                $size  = Storage::disk('public')->size($message->downloaded_file);
                Storage::disk('public')->delete($message->downloaded_file);
                $message->update([
                    'downloaded_file'    => null,
                    'file_downloaded_at' => null,
                ]);
                $deleted++;
                $freed += $size;
            }
        }

        return response()->json([
            'success'  => true,
            'deleted'  => $deleted,
            'freed_mb' => round($freed / 1048576, 2),
            'message'  => "Удалено {$deleted} неиспользуемых файлов",
        ]);
    }
}
