<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramChat;
use App\Models\TelegramPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    public function create(int $chatId)
    {
        $chat = TelegramChat::findOrFail($chatId);

        if (!$chat->isChannel()) {
            return redirect()->back()->with('error', 'Посты можно создавать только в каналах');
        }

        return view('telegram.posts.create', ['chat' => $chat]);
    }

    public function store(Request $request, int $chatId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $chat = TelegramChat::findOrFail($chatId);

        $result = $this->gateway->createPost($chatId, $request->content);

        if (($result['status'] ?? '') === 'ok' && isset($result['message_id'])) {
            TelegramPost::create([
                'chat_id' => $chatId,
                'message_id' => $result['message_id'],
                'content' => $request->content,
                'posted_at' => now(),
            ]);

            return redirect()->route('telegram.chat.show', $chatId)
                ->with('success', 'Пост создан');
        }

        return redirect()->back()
            ->with('error', 'Ошибка создания поста');
    }
}
