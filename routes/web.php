<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\ChatController;
use App\Http\Controllers\Telegram\MessageController;
use App\Http\Controllers\Telegram\PostController;
use App\Http\Controllers\Telegram\ChatExclusionController;
use App\Http\Controllers\Telegram\CommentController;
use App\Http\Controllers\Telegram\FileDownloadController;
use App\Http\Controllers\Telegram\ProxyController;
use App\Models\TelegramUserComment;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Главная страница (может быть перенаправление на чаты)
Route::redirect('/', '/telegram/chats');

// ========== TELEGRAM ROUTES ==========
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

    // Комментарии
    Route::get('/comments/unprocessed', [ChatExclusionController::class, 'unprocessedPage'])->name('telegram.comments.unprocessed');

    // ===== API МАРШРУТЫ (внутри префикса telegram) =====

    // Количество комментариев
    Route::get('/api/comments/unprocessed/count', function () {
        $count = TelegramUserComment::unprocessed()->count();
        return response()->json(['count' => $count]);
    })->name('telegram.comments.count');

    // Скачивание файлов (исправленный маршрут с двумя параметрами)
    Route::post('/api/messages/{chatId}/{messageId}/download', [FileDownloadController::class, 'download'])
        ->name('telegram.download');

    // Получение комментариев к посту
    Route::get('/api/posts/{postId}/comments', function ($postId) {
        $comments = TelegramUserComment::where('post_id', $postId)
            ->orderBy('date', 'asc')
            ->get();
        return response()->json(['comments' => $comments]);
    });

    // // Прокси
    // Route::post('/api/proxy/change', [ProxyController::class, 'change']);
    // Route::get('/api/proxy/list', [ProxyController::class, 'list']);
    // Route::post('/api/proxy/test/{id}', [ProxyController::class, 'test']);
    // Route::post('/api/proxy/parse', [ProxyController::class, 'parse']);
    // Route::post('/api/proxy/add', [ProxyController::class, 'add']);

    // Получение сообщений чата (для AJAX обновления)
    Route::get('/api/chat/{chatId}/messages', function ($chatId) {
        $messages = \App\Models\TelegramMessage::where('chat_id', $chatId)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();
        return response()->json($messages);
    });

    // Отправка сообщений
    Route::post('/chat/{id}/send', [MessageController::class, 'send'])->name('telegram.chat.send');

    // Создание постов
    Route::get('/channel/{id}/create-post', [PostController::class, 'create'])->name('telegram.post.create');
    Route::post('/channel/{id}/store-post', [PostController::class, 'store'])->name('telegram.post.store');
Route::get('/api/posts/{chatId}/{postId}/comments', [ChatController::class, 'getPostComments'])
    ->name('telegram.post.comments');

// Комментарии
        Route::get('/comments/unprocessed', [CommentController::class, 'unprocessed'])->name('telegram.comments.unprocessed');
    Route::get('/api/comments/unprocessed/count', [CommentController::class, 'unprocessedCount'])->name('telegram.comments.count');
    Route::post('/api/comments/{comment}/processed', [CommentController::class, 'markProcessed'])->name('telegram.comment.processed');
});
