<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramUserComment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Получить количество необработанных комментариев
     */
    public function unprocessedCount()
    {
        $count = TelegramUserComment::unprocessed()->count();
        
        return response()->json([
            'count' => $count
        ]);
    }

    /**
     * Получить список необработанных комментариев
     */
    public function unprocessed(Request $request)
    {
        $comments = TelegramUserComment::with(['chat', 'post'])
            ->unprocessed()
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json($comments);
    }

    /**
     * Отметить комментарий как обработанный
     */
    public function markProcessed(Request $request, int $commentId)
    {
        $comment = TelegramUserComment::find($commentId);
        
        if (!$comment) {
            return response()->json(['error' => 'Комментарий не найден'], 404);
        }

        $comment->markAsProcessed(auth()->id());

        return response()->json(['success' => true]);
    }
}