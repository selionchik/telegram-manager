<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
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

    /**
     * Отправка сообщения (заглушка - Gateway пока не умеет)
     */
    public function send(Request $request, int $chatId)
    {
        $request->validate([
            'message' => 'required|string',
            'reply_to' => 'nullable|integer',
        ]);

        // TODO: добавить отправку в Gateway
        Log::info('📤 Отправка сообщения (заглушка)', [
            'chat_id' => $chatId,
            'message' => $request->message,
            'reply_to' => $request->reply_to
        ]);

        return redirect()->back()->with('info', 'Отправка сообщений временно недоступна');
    }
}