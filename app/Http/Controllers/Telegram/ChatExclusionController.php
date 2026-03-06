<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Models\TelegramUserComment;
use Illuminate\Http\Request;

class ChatExclusionController extends Controller
{
    /**
     * Исключить чат из парсинга
     */
    public function exclude(Request $request, int $chatId)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return response()->json(['error' => 'Чат не найден'], 404);
        }

        $chat->exclude($validated['reason'] ?? null);

        return response()->json(['success' => true]);
    }

    /**
     * Вернуть чат в парсинг
     */
    public function include(int $chatId)
    {
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return response()->json(['error' => 'Чат не найден'], 404);
        }

        $chat->include();

        return response()->json(['success' => true]);
    }

    /**
     * Получить список исключённых чатов
     */
    public function excluded(Request $request)
    {
        $accountId = $request->get('account_id');
        
        $query = TelegramChat::excluded();
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $chats = $query->orderBy('excluded_at', 'desc')->get();

        return response()->json([
            'data' => $chats,
            'count' => $chats->count()
        ]);
    }

    /**
     * Страница исключённых чатов
     */
    public function index(Request $request)
    {
        $accountId = $request->get('account_id');
        
        $chats = TelegramChat::excluded()
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->orderBy('excluded_at', 'desc')
            ->paginate(50);

        return view('telegram.excluded.index', [
            'chats' => $chats
        ]);
    }

    /**
     * Переключить статус исключения
     */
    public function toggle(Request $request, int $chatId)
    {
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return redirect()->back()->with('error', 'Чат не найден');
        }

        if ($chat->is_excluded) {
            $chat->include();
            $message = 'Чат возвращён в парсинг';
        } else {
            $chat->exclude($request->get('reason'));
            $message = 'Чат исключён из парсинга';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Получить необработанные комментарии (API)
     */
    public function unprocessedComments(Request $request)
    {
        $comments = TelegramUserComment::with(['chat', 'post'])
            ->unprocessed()
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json($comments);
    }

    /**
     * Страница необработанных комментариев
     */
    public function unprocessedPage(Request $request)
    {
        $comments = TelegramUserComment::with(['chat', 'post'])
            ->unprocessed()
            ->orderBy('date', 'desc')
            ->paginate(50);

        return view('telegram.comments.unprocessed', [
            'comments' => $comments
        ]);
    }

    /**
     * Отметить комментарий как обработанный
     */
    public function markCommentProcessed(Request $request, int $commentId)
    {
        $comment = TelegramUserComment::find($commentId);
        
        if (!$comment) {
            return response()->json(['error' => 'Комментарий не найден'], 404);
        }

        $comment->markAsProcessed(auth()->id());

        return response()->json(['success' => true]);
    }
}