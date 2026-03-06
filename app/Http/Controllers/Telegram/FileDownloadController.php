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

        $message = TelegramMessage::where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->first();

        if (!$message) {
            Log::error('❌ Сообщение не найдено в БД', [
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);
            return response()->json(['error' => 'Message not found in DB'], 404);
        }

        $message->update(['file_url_clicked' => true]);

        $result = $this->gateway->downloadFile($chatId, $messageId);

        if (($result['status'] ?? '') === 'ok') {
            $message->update([
                'downloaded_file' => $result['file'],
                'file_downloaded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'url' => 'http://4af690bcc2b8.vps.myjino.ru' . $result['web_url'],
                'type' => $this->guessType($result['web_url']),
                'cached' => false
            ]);
        }

        Log::error('❌ Ошибка скачивания через Gateway', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'error' => $result['error'] ?? 'Unknown error'
        ]);

        return response()->json([
            'error' => $result['error'] ?? 'Download failed'
        ], 500);
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