<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\ChatController;
use App\Http\Controllers\Telegram\MessageController;
use App\Http\Controllers\Telegram\PostController;
use App\Http\Controllers\Telegram\ChatExclusionController;
use App\Http\Controllers\Telegram\FileDownloadController;
use App\Models\TelegramUserComment;

// ========== TELEGRAM ROUTES ==========
Route::prefix('telegram')->group(function () {

    // Существующие маршруты...
    Route::get('/chats', [ChatController::class, 'index'])->name('telegram.chats');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('telegram.chat.show');
    Route::post('/chat/{id}/send', [MessageController::class, 'send'])->name('telegram.chat.send');

    // Посты
    Route::get('/channel/{id}/create-post', [PostController::class, 'create'])->name('telegram.post.create');
    Route::post('/channel/{id}/store-post', [PostController::class, 'store'])->name('telegram.post.store');
    Route::get('/post/{chatId}/{messageId}/edit', [PostController::class, 'edit'])->name('telegram.post.edit');
    Route::put('/post/{chatId}/{messageId}', [PostController::class, 'update'])->name('telegram.post.update');

    // Исключения
    Route::get('/excluded', [ChatExclusionController::class, 'index'])->name('telegram.excluded');
    Route::post('/chat/{id}/toggle-exclude', [ChatExclusionController::class, 'toggle'])->name('telegram.chat.toggle');

    // Комментарии
    Route::get('/comments/unprocessed', [ChatExclusionController::class, 'unprocessedPage'])->name('telegram.comments.unprocessed');

    // ===== НОВЫЙ МАРШРУТ =====
    Route::get('/api/comments/unprocessed/count', function () {
        $count = TelegramUserComment::unprocessed()->count();
        return response()->json(['count' => $count]);
    })->name('telegram.comments.count');

    // Скачивание файлов (уже есть)
    Route::post('/api/files/cleanup', [FileDownloadController::class, 'cleanup']);
    Route::post('/api/messages/{message}/download', [FileDownloadController::class, 'download'])->name('telegram.download');
    Route::get('/telegram/api/posts/{postId}/comments', function ($postId) {
        $comments = \App\Models\TelegramUserComment::where('post_id', $postId)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'comments' => $comments
        ]);
    });

    Route::post('/api/proxy/change', [App\Http\Controllers\Telegram\ProxyController::class, 'change'])->name('telegram.proxy.change');
    Route::get('/api/proxy/list', [App\Http\Controllers\Telegram\ProxyController::class, 'list']);
    Route::post('/api/proxy/test/{id}', [App\Http\Controllers\Telegram\ProxyController::class, 'test']);


    // Маршрут для получения сообщений чата
    Route::get('/api/chat/{chatId}/messages', function ($chatId) {
        $messages = \App\Models\TelegramMessage::where('chat_id', $chatId)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();
        return response()->json($messages);
    });


Route::post('/api/proxy/parse', [App\Http\Controllers\Telegram\ProxyController::class, 'parse']);
Route::post('/api/proxy/add', [App\Http\Controllers\Telegram\ProxyController::class, 'add']);    

// Маршрут для отображения файлов
Route::get('/storage/{path}', function($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '.*')->name('telegram.file');
});
