<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\ChatExclusionController;
use App\Http\Controllers\Telegram\FileDownloadController;
use App\Http\Controllers\Telegram\CommentController;

Route::prefix('api')->group(function () {
    
    // Управление исключениями чатов
    Route::post('/chats/{chat}/exclude', [ChatExclusionController::class, 'exclude']);
    Route::post('/chats/{chat}/include', [ChatExclusionController::class, 'include']);
    Route::get('/chats/excluded', [ChatExclusionController::class, 'excluded']);
    Route::get('/chats/active', [ChatExclusionController::class, 'active']);
    Route::post('/chats/exclude-multiple', [ChatExclusionController::class, 'excludeMultiple']);

    // КОММЕНТАРИИ - используем отдельный контроллер
    Route::get('/comments/unprocessed/count', [CommentController::class, 'unprocessedCount']);
    Route::get('/comments/unprocessed', [CommentController::class, 'unprocessed']);
    Route::post('/comments/{comment}/processed', [CommentController::class, 'markProcessed']);
    
    // Скачивание файлов
    Route::post('/messages/{message}/download', [FileDownloadController::class, 'download']);
    Route::post('/files/cleanup', [FileDownloadController::class, 'cleanup']);
    
});