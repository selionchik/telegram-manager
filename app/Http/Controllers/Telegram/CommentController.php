<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramUserComment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Отметить комментарий как обработанный
     */
    public function markProcessed(Request $request, int $commentId)
    {
        $comment = TelegramUserComment::findOrFail($commentId);
        $comment->markAsProcessed(auth()->id());
        
        return response()->json(['success' => true]);
    }

    /**
     * Получить список необработанных комментариев
     */
    public function unprocessed(Request $request)
    {
        $comments = TelegramUserComment::with(['chat'])
            ->unprocessed()
            ->orderBy('date', 'desc')
            ->paginate(50);

        return view('telegram.comments.unprocessed', [
            'comments' => $comments
        ]);
    }

    /**
     * API для получения количества необработанных
     */
    public function unprocessedCount()
    {
        $count = TelegramUserComment::unprocessed()->count();
        return response()->json(['count' => $count]);
    }
}