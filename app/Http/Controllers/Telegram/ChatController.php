<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Services\Telegram\TelegramParserService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected TelegramParserService $parser;

    public function __construct(TelegramParserService $parser)
    {
        $this->parser = $parser;
    }

    public function index(Request $request)
    {
        $sort = $request->get('sort', 'default');
        
        $chats = TelegramChat::active()
            ->ordered($sort)
            ->paginate(50);

        return view('telegram.chats.index', [
            'chats' => $chats,
            'currentSort' => $sort,
            'sortOptions' => ['default', 'alphabet', 'excluded']
        ]);
    }

    public function show(int $id)
    {
        $chat = TelegramChat::with('messages')->findOrFail($id);
        
        $messages = $chat->messages()
            ->orderBy('date', 'desc')
            ->paginate(50);

        return view('telegram.chats.show', [
            'chat' => $chat,
            'messages' => $messages
        ]);
    }
}