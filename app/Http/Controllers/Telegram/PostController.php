<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Форма создания поста
     */
    public function create(int $chatId)
    {
        $chat = TelegramChat::findOrFail($chatId);
        
        if (!$chat->isChannel()) {
            return redirect()->back()->with('error', 'Посты можно создавать только в каналах');
        }

        return view('telegram.posts.create', ['chat' => $chat]);
    }

    /**
     * Сохранение поста (заглушка)
     */
    public function store(Request $request, int $chatId)
    {
        // TODO: добавить создание поста в Gateway
        Log::info('📝 Создание поста (заглушка)', [
            'chat_id' => $chatId,
            'content' => $request->content
        ]);

        return redirect()->route('telegram.chat.show', $chatId)
            ->with('info', 'Создание постов временно недоступно');
    }

    /**
     * Форма редактирования поста (заглушка)
     */
    public function edit(int $chatId, int $messageId)
    {
        return redirect()->back()->with('info', 'Редактирование временно недоступно');
    }
}