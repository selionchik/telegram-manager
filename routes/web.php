<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\ChatController;
use App\Http\Controllers\Telegram\MessageController;
use App\Http\Controllers\Telegram\PostController;
use App\Http\Controllers\Telegram\ChatExclusionController;
use App\Http\Controllers\Telegram\CommentController;
use App\Http\Controllers\Telegram\FileDownloadController;
use App\Models\TelegramUserComment;

Route::redirect('/', '/telegram/chats');

Route::prefix('telegram')->group(function () {

    // Чаты
    Route::get('/chats', [ChatController::class, 'index'])->name('telegram.chats');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('telegram.chat.show');

    // Отправка сообщений
    Route::post('/chat/{id}/send', [MessageController::class, 'send'])->name('telegram.chat.send');

    // Посты
    Route::get('/channel/{id}/create-post', [PostController::class, 'create'])->name('telegram.post.create');
    Route::post('/channel/{id}/store-post', [PostController::class, 'store'])->name('telegram.post.store');
    Route::get('/post/{chatId}/{messageId}/edit', [PostController::class, 'edit'])->name('telegram.post.edit');
    Route::put('/post/{chatId}/{messageId}', [PostController::class, 'update'])->name('telegram.post.update');

    // Исключения
    Route::get('/excluded', [ChatExclusionController::class, 'index'])->name('telegram.excluded');
    Route::post('/chat/{id}/toggle-exclude', [ChatExclusionController::class, 'toggle'])->name('telegram.chat.toggle');

    // Комментарии (объединены)
    Route::get('/comments/unprocessed', [CommentController::class, 'unprocessed'])->name('telegram.comments.unprocessed');
    Route::get('/api/comments/unprocessed/count', [CommentController::class, 'unprocessedCount'])->name('telegram.comments.count');
    Route::post('/api/comments/{comment}/processed', [CommentController::class, 'markProcessed'])->name('telegram.comment.processed');
    Route::get('/api/posts/{chatId}/{postId}/comments', [ChatController::class, 'getPostComments'])->name('telegram.post.comments');

    // Скачивание файлов (объединены)
    Route::post('/api/messages/{chatId}/{messageId}/download', [FileDownloadController::class, 'download'])->name('telegram.download');
    Route::get('/api/size/{chatId}/{messageId}', [FileDownloadController::class, 'getSize'])->name('telegram.download.size');
    Route::get('/download-sse/{chatId}/{messageId}', [FileDownloadController::class, 'downloadWithProgress'])->name('telegram.download.sse');
    Route::get('/progress/{chatId}/{messageId}', [FileDownloadController::class, 'progress'])->name('telegram.download.progress');

    // Получение сообщений чата
    Route::get('/api/chat/{chatId}/messages', function ($chatId) {
        return \App\Models\TelegramMessage::where('chat_id', $chatId)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();
    });
});