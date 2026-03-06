<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramAccount;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    public function send(Request $request, int $chatId)
    {
        $request->validate([
            'message' => 'required|string',
            'reply_to' => 'nullable|integer',
        ]);

        $chat = TelegramChat::findOrFail($chatId);

        // Получаем аккаунт для from_id
        $account = TelegramAccount::where('status', 'connected')->first();

        $result = $this->gateway->sendMessage($chatId, $request->message, $request->reply_to);

        if (($result['status'] ?? '') === 'ok' && isset($result['message_id'])) {
            // Сохраняем отправленное сообщение в БД
            TelegramMessage::create([
                'chat_id' => $chatId,
                'message_id' => $result['message_id'],
                'from_id' => $account?->tg_id ?? 0,
                'date' => now(),
                'text' => $request->message,
                'reply_to_msg_id' => $request->reply_to,
                'out' => true,
                'has_media' => false,
                'media_type' => null,
                'processed' => false,
            ]);

            return redirect()->back()->with('success', 'Сообщение отправлено');
        }

        return redirect()->back()->with('error', 'Ошибка отправки: ' . ($result['message'] ?? 'Unknown error'));
    }
}
