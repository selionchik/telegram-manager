<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\ChatExclusionService;
use App\Models\TelegramChat;
use App\Models\TelegramUserComment;
use Illuminate\Http\Request;

class ChatExclusionController extends Controller
{
    protected ChatExclusionService $exclusionService;

    public function __construct(ChatExclusionService $exclusionService)
    {
        $this->exclusionService = $exclusionService;
    }

    /**
     * Исключить чат
     */
    public function exclude(Request $request, int $chatId)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $result = $this->exclusionService->excludeChat($chatId, $validated['reason'] ?? null);

        if (!$result) {
            return response()->json(['error' => 'Чат не найден'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Вернуть чат
     */
    public function include(int $chatId)
    {
        $result = $this->exclusionService->includeChat($chatId);

        if (!$result) {
            return response()->json(['error' => 'Чат не найден'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Получить список исключённых чатов
     */
    public function excluded(Request $request)
    {
        $accountId = $request->get('account_id');
        
        $chats = $this->exclusionService->getExcludedChats($accountId);

        return response()->json([
            'data' => $chats,
            'count' => $chats->count()
        ]);
    }

    /**
     * Получить список активных чатов
     */
    public function active(Request $request)
    {
        $accountId = $request->get('account_id');
        $sort = $request->get('sort', 'default');
        
        $chats = $this->exclusionService->getActiveChats($accountId, $sort);

        return response()->json([
            'data' => $chats,
            'count' => $chats->count()
        ]);
    }

    /**
     * Массовое исключение
     */
    public function excludeMultiple(Request $request)
    {
        $validated = $request->validate([
            'chat_ids' => 'required|array',
            'chat_ids.*' => 'integer',
            'reason' => 'nullable|string',
        ]);

        $results = $this->exclusionService->excludeMultiple(
            $validated['chat_ids'],
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'results' => $results
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

    /**
     * Получить необработанные комментарии
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
     * Страница исключённых чатов
     */
    public function index(Request $request)
    {
        $accountId = $request->get('account_id');
        
        $chats = $this->exclusionService->getExcludedChats($accountId);

        return view('telegram.excluded.index', [
            'chats' => $chats
        ]);
    }

    /**
     * Переключить статус исключения (веб)
     */
    public function toggle(Request $request, int $chatId)
    {
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return redirect()->back()->with('error', 'Чат не найден');
        }

        if ($chat->is_excluded) {
            $this->exclusionService->includeChat($chatId);
            $message = 'Чат возвращён в парсинг';
        } else {
            $this->exclusionService->excludeChat($chatId, $request->get('reason'));
            $message = 'Чат исключён из парсинга';
        }

        return redirect()->back()->with('success', $message);
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
    
}