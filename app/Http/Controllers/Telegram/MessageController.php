<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Services\Telegram\TelegramParserService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected TelegramParserService $parser;

    public function __construct(TelegramParserService $parser)
    {
        $this->parser = $parser;
    }

    public function send(Request $request, int $chatId)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'reply_to' => 'nullable|integer',
        ]);

        $chat = TelegramChat::findOrFail($chatId);

        $result = $this->parser->sendMessage(
            $chat,
            $validated['message'],
            $validated['reply_to'] ?? null
        );

        if ($result) {
            return redirect()->back()->with('success', 'Сообщение отправлено');
        }

        return redirect()->back()->with('error', 'Ошибка отправки');
    }
}