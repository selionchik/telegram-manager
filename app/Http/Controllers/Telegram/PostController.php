<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Models\TelegramPost;
use App\Services\Telegram\TelegramParserService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    protected TelegramParserService $parser;

    public function __construct(TelegramParserService $parser)
    {
        $this->parser = $parser;
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
        $validated = $request->validate([
            'content' => 'required|string',
            'media' => 'nullable|array',
        ]);

        $chat = TelegramChat::findOrFail($chatId);

        $result = $this->parser->createPost($chat, $validated['content']);

        if ($result) {
            return redirect()->route('telegram.chat.show', $chatId)
                ->with('success', 'Пост создан');
        }

        return redirect()->back()->with('error', 'Ошибка создания поста');
    }

    public function edit(int $chatId, int $messageId)
    {
        $post = TelegramPost::where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->firstOrFail();

        return view('telegram.posts.edit', ['post' => $post]);
    }

    public function update(Request $request, int $chatId, int $messageId)
    {
        // TODO: реализовать редактирование поста через API
        return redirect()->back()->with('info', 'Функция в разработке');
    }
}