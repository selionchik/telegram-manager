<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileDownloadController extends Controller
{
    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Скачать файл из сообщения
     */
    public function download(Request $request, int $chatId, int $messageId)
    {
        Log::info("📥 Запрос на скачивание через Gateway", [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);

        // Gateway теперь сам отдаёт файл, мы просто проксируем
        $gatewayUrl = config('telegram.gateway.url') . "/download/{$chatId}/{$messageId}";

        // Создаём HTTP-клиент с streaming
        $client = new \GuzzleHttp\Client([
            'stream' => true,  // важно!
            'headers' => [
                'Authorization' => 'Bearer ' . config('telegram.gateway.token')
            ]
        ]);

        try {
            $response = $client->get($gatewayUrl);

            // Проксируем ответ клиенту
            return response()->stream(
                function () use ($response) {
                    $body = $response->getBody();
                    while (!$body->eof()) {
                        echo $body->read(8192);  // 8KB чанки
                        ob_flush();
                        flush();
                    }
                },
                $response->getStatusCode(),
                [
                    'Content-Type' => $response->getHeaderLine('Content-Type'),
                    'Content-Disposition' => $response->getHeaderLine('Content-Disposition'),
                    'Cache-Control' => 'no-cache',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Gateway download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
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
}
