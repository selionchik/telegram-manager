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
    
    // Константа с размером чанка
    const CHUNK_SIZE = 8192;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Скачать файл из сообщения (используется в старом интерфейсе)
     */
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
            $response = $client->get($gatewayUrl, [
                'progress' => function(
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) use ($chatId, $messageId) {
                    if ($downloadTotal > 0) {
                        $percent = (int) round(($downloadedBytes / $downloadTotal) * 100);
                        Cache::put("download_{$chatId}_{$messageId}_progress", $percent, 600);
                        Log::info("📊 Guzzle progress {$chatId}/{$messageId}: {$percent}% ({$downloadedBytes}/{$downloadTotal})");
                    }
                }
            ]);

            $disposition = $response->getHeaderLine('Content-Disposition');
            preg_match('/filename="?([^"]+)"?/', $disposition, $matches);
            $fileName = $matches[1] ?? "{$chatId}_{$messageId}.bin";

            $savePath = storage_path("app/public/telegram/downloads/{$fileName}");
            $saveDir = dirname($savePath);

            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }

            $fileHandle = fopen($savePath, 'w');
            $totalSize = $response->getHeaderLine('Content-Length') ?: null;

            Log::info('🔍 Заголовки Gateway', [
                'content-length' => $totalSize,
                'content-type' => $response->getHeaderLine('Content-Type'),
                'content-disposition' => $disposition
            ]);

            return response()->stream(
                function () use ($response, $fileHandle, $totalSize, $chatId, $messageId, $fileName) {
                    $body = $response->getBody();
                    $downloaded = 0;

                    while (!$body->eof()) {
                        $chunk = $body->read(self::CHUNK_SIZE);
                        fwrite($fileHandle, $chunk);
                        echo $chunk;
                        
                        $downloaded += strlen($chunk);
                        
                        ob_flush();
                        flush();
                    }

                    fclose($fileHandle);

                    TelegramMessage::where('chat_id', $chatId)
                        ->where('message_id', $messageId)
                        ->update([
                            'downloaded_file' => "telegram/downloads/{$fileName}",
                            'file_downloaded_at' => now(),
                        ]);
                },
                200,
                [
                    'Content-Type' => $response->getHeaderLine('Content-Type'),
                    'Content-Disposition' => $disposition,
                ]
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
     * Получить размер файла перед скачиванием
     */
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
                    'chunkSize' => self::CHUNK_SIZE,
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => $data['error'] ?? 'Unknown error'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Size request error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Скачивание с SSE-прогрессом (новый интерфейс)
     */
    public function downloadWithProgress(Request $request, int $chatId, int $messageId)
    {
        Log::info("📥 Запрос на скачивание с SSE", [
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

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($client, $gatewayUrl, $chatId, $messageId) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            
            ini_set('zlib.output_compression', 0);
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            
            try {
                $guzzleResponse = $client->get($gatewayUrl, [
                    'progress' => function(
                        $downloadTotal,
                        $downloadedBytes,
                        $uploadTotal,
                        $uploadedBytes
                    ) use ($chatId, $messageId) {
                        if ($downloadTotal > 0) {
                            $percent = (int) round(($downloadedBytes / $downloadTotal) * 100);
                            
                            echo "event: progress\n";
                            echo "data: " . json_encode([
                                'total' => $downloadTotal,
                                'downloaded' => $downloadedBytes,
                                'percent' => $percent
                            ]) . "\n\n";
                            
                            ob_flush();
                            flush();
                            
                            Log::info("📊 SSE progress {$chatId}/{$messageId}: {$percent}% ({$downloadedBytes}/{$downloadTotal})");
                            
                            if ($percent < 100) {
                                usleep(50000);
                            }
                        }
                    }
                ]);

                $disposition = $guzzleResponse->getHeaderLine('Content-Disposition');
                preg_match('/filename="?([^"]+)"?/', $disposition, $matches);
                $fileName = $matches[1] ?? "{$chatId}_{$messageId}.bin";

                $savePath = storage_path("app/public/telegram/downloads/{$fileName}");
                $saveDir = dirname($savePath);

                if (!is_dir($saveDir)) {
                    mkdir($saveDir, 0755, true);
                }

                $fileHandle = fopen($savePath, 'w');
                $body = $guzzleResponse->getBody();
                
                while (!$body->eof()) {
                    $chunk = $body->read(self::CHUNK_SIZE);
                    fwrite($fileHandle, $chunk);
                }
                
                fclose($fileHandle);

                TelegramMessage::where('chat_id', $chatId)
                    ->where('message_id', $messageId)
                    ->update([
                        'downloaded_file' => "telegram/downloads/{$fileName}",
                        'file_downloaded_at' => now(),
                    ]);

                $url = asset('storage/telegram/downloads/' . $fileName);
                echo "event: complete\n";
                echo "data: " . json_encode(['url' => $url]) . "\n\n";
                
                ob_flush();
                flush();

            } catch (\Exception $e) {
                Log::error('Download error', ['error' => $e->getMessage()]);
                echo "event: error\n";
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        });
        
        return $response;
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
}