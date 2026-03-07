<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FileDownloadController extends Controller
{
    protected GatewayService $gateway;
    public $chunkSize = 8192;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Скачать файл из сообщения
     */
    // public function download(Request $request, int $chatId, int $messageId)
    // {
    //     Log::info("📥 Запрос на скачивание через Gateway", [
    //         'chat_id' => $chatId,
    //         'message_id' => $messageId
    //     ]);

    //     $gatewayUrl = config('telegram.gateway.url') . "/download/{$chatId}/{$messageId}";

    //     $client = new \GuzzleHttp\Client([
    //         'stream' => true,
    //         'headers' => [
    //             'Authorization' => 'Bearer ' . config('telegram.gateway.token')
    //         ]
    //     ]);

    //     try {
    //         $response = $client->get($gatewayUrl);

    //         Log::info('🔍 Заголовки Gateway', [
    //             'content-length' => $response->getHeaderLine('Content-Length'),
    //             'content-type' => $response->getHeaderLine('Content-Type'),
    //             'content-disposition' => $response->getHeaderLine('Content-Disposition')
    //         ]);
    //         // Достаём имя файла из заголовка Content-Disposition
    //         $disposition = $response->getHeaderLine('Content-Disposition');
    //         preg_match('/filename="?([^"]+)"?/', $disposition, $matches);
    //         $fileName = $matches[1] ?? "{$chatId}_{$messageId}.bin";

    //         // Путь для сохранения
    //         $savePath = public_path("storage/telegram/downloads/{$fileName}");
    //         $saveDir = dirname($savePath);

    //         if (!is_dir($saveDir)) {
    //             mkdir($saveDir, 0755, true);
    //         }

    //         $fileHandle = fopen($savePath, 'w');

    //         // Общий размер для прогресса
    //         $totalSize = $response->getHeaderLine('Content-Length') ?: null;

    //         return response()->stream(
    //             function () use ($response, $fileHandle, $totalSize, $chatId, $messageId, $fileName) {
    //                 $body = $response->getBody();
    //                 $downloaded = 0;


    //                 $logCounter = 0;
    //                 while (!$body->eof()) {
    //                     $chunk = $body->read(8192);

    //                     // Пишем в файл
    //                     fwrite($fileHandle, $chunk);

    //                     // Отправляем клиенту
    //                     echo $chunk;

    //                     $downloaded += strlen($chunk);

    //                     // Сохраняем прогресс в кеш
    //                     if ($totalSize) {
    //                         $percent = (int) round(($downloaded / $totalSize) * 100);
    //                         Cache::put("download_{$chatId}_{$messageId}_progress", $percent, 600); // храним 10 минут
    //                         // Логируем каждый 10% или первый запрос
    //                         if ($percent % 10 == 0 || $logCounter == 0) {
    //                             Log::info("📊 Прогресс {$chatId}/{$messageId}: {$percent}% ({$downloaded}/{$totalSize})");
    //                             $logCounter++;
    //                         }
    //                     }

    //                     ob_flush();
    //                     flush();
    //                 }

    //                 fclose($fileHandle);

    //                 // Обновляем БД
    //                 TelegramMessage::where('chat_id', $chatId)
    //                     ->where('message_id', $messageId)
    //                     ->update([
    //                         'downloaded_file' => "telegram/downloads/{$fileName}",
    //                         'file_downloaded_at' => now(),
    //                     ]);
    //             },
    //             200,
    //             [
    //                 'Content-Type' => $response->getHeaderLine('Content-Type'),
    //                 'Content-Disposition' => $disposition,
    //             ]
    //         );
    //     } catch (\Exception $e) {
    //         Log::error('Gateway download error', [
    //             'chat_id' => $chatId,
    //             'message_id' => $messageId,
    //             'error' => $e->getMessage()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Download failed: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function download(Request $request, int $chatId, int $messageId)
    {
        Log::info("📥 Запрос на скачивание через Gateway", [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);

        $gatewayUrl = config('telegram.gateway.url') . "/download/{$chatId}/{$messageId}";

        $client = new \GuzzleHttp\Client([
            'stream' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . config('telegram.gateway.token')
            ]
        ]);

        try {
            $response = $client->get($gatewayUrl);

            // Достаём имя файла из заголовка Content-Disposition
            $disposition = $response->getHeaderLine('Content-Disposition');
            preg_match('/filename="?([^"]+)"?/', $disposition, $matches);
            $fileName = $matches[1] ?? "{$chatId}_{$messageId}.bin";

            // Путь для сохранения
            $savePath = storage_path("app/public/telegram/downloads/{$fileName}");
            $saveDir = dirname($savePath);

            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }

            $fileHandle = fopen($savePath, 'w');

            // Общий размер для прогресса
            $totalSize = $response->getHeaderLine('Content-Length') ?: null;

            // Логируем заголовки для отладки
            Log::info('🔍 Заголовки Gateway', [
                'content-length' => $totalSize,
                'content-type' => $response->getHeaderLine('Content-Type'),
                'content-disposition' => $disposition
            ]);

            // Формируем ответ с правильными заголовками для браузера
            $headers = [
                'Content-Type' => $response->getHeaderLine('Content-Type'),
                'Content-Disposition' => $disposition,
                'Accept-Ranges' => 'bytes',  // Добавляем для поддержки прогресса
            ];

            if ($totalSize) {
                $headers['Content-Length'] = $totalSize;
            }

            $chunkSize = $this->chunkSize;
            return response()->stream(
                function () use ($response, $fileHandle, $totalSize, $chatId, $messageId, $fileName, $chunkSize) {
                    $body = $response->getBody();
                    $downloaded = 0;
                    $logCounter = 0;

                    while (!$body->eof()) {
                        $chunk = $body->read($chunkSize);

                        // Пишем в файл
                        fwrite($fileHandle, $chunk);

                        // Отправляем клиенту
                        echo $chunk;

                        $downloaded += strlen($chunk);

                        // Сохраняем прогресс в кеш
                        if ($totalSize) {
                            $percent = (int) round(($downloaded / $totalSize) * 100);
                            Cache::put("download_{$chatId}_{$messageId}_progress", $percent, 600);

                            // Логируем каждый 5%
                            if ($percent % 5 == 0 || $logCounter == 0) {
                                Log::info("📊 Прогресс {$chatId}/{$messageId}: {$percent}% ({$downloaded}/{$totalSize})");
                                $logCounter++;
                            }
                        }

                        ob_flush();
                        flush();
                    }

                    fclose($fileHandle);

                    // Обновляем БД
                    TelegramMessage::where('chat_id', $chatId)
                        ->where('message_id', $messageId)
                        ->update([
                            'downloaded_file' => "telegram/downloads/{$fileName}",
                            'file_downloaded_at' => now(),
                        ]);
                },
                200,
                $headers
            );
        } catch (\Exception $e) {
            Log::error('Gateway download error', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Определить тип файла по URL
     */
    protected function guessType(string $url): string
    {
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
            return 'photo';
        }
        if (preg_match('/\.(mp4|mov|avi|mkv)$/i', $url)) {
            return 'video';
        }
        if (preg_match('/\.(mp3|wav|ogg)$/i', $url)) {
            return 'audio';
        }
        return 'document';
    }

    public function progress(int $chatId, int $messageId)
    {
        $progress = Cache::get("download_{$chatId}_{$messageId}_progress", 0);

        // Проверяем, может файл уже полностью скачан
        $message = TelegramMessage::where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->first();

        // Если файл есть в БД и существует на диске
        if ($message && $message->display_url && file_exists(public_path("storage/{$message->downloaded_file}"))) {
            return response()->json([
                'progress' => 100,
                'url' => $message->display_url,
                'done' => true
            ]);
        }

        return response()->json([
            'progress' => (int)$progress,
            'done' => false
        ]);
    }

public function getSize(int $chatId, int $messageId)
{
    $gatewayUrl = config('telegram.gateway.url') . "/api/size/{$chatId}/{$messageId}";
    
    $client = new \GuzzleHttp\Client();
    
    try {
        $response = $client->get($gatewayUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . config('telegram.gateway.token')
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        
        if (isset($data['status']) && $data['status'] === 'ok') {
            return response()->json([
                'success' => true,
                'size' => $data['size'],
                'chunkSize' => $this->chunkSize,
            ]);
        }
        
        return response()->json([
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error'
        ], 500);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}  
}
