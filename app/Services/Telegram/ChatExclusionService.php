<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use Illuminate\Support\Facades\Log;

class ChatExclusionService
{
    /**
     * Исключить чат из парсинга
     */
    public function excludeChat(int $chatId, string $reason = null): bool
    {
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return false;
        }

        $chat->exclude($reason);
        
        Log::info('Чат исключён', [
            'chat_id' => $chatId,
            'title' => $chat->title,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Вернуть чат в парсинг
     */
    public function includeChat(int $chatId): bool
    {
        $chat = TelegramChat::find($chatId);
        
        if (!$chat) {
            return false;
        }

        $chat->include();
        
        Log::info('Чат возвращён', [
            'chat_id' => $chatId,
            'title' => $chat->title
        ]);

        return true;
    }

    /**
     * Исключить несколько чатов
     */
    public function excludeMultiple(array $chatIds, string $reason = null): array
    {
        $results = [];
        
        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->excludeChat($chatId, $reason);
        }

        return $results;
    }

    /**
     * Получить все исключённые чаты
     */
    public function getExcludedChats(int $accountId = null)
    {
        $query = TelegramChat::excluded();
        
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->orderBy('excluded_at', 'desc')->get();
    }

    /**
     * Получить активные чаты (не исключённые)
     */
    public function getActiveChats(int $accountId = null, string $sort = 'default')
    {
        $query = TelegramChat::active();
        
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->ordered($sort)->get();
    }

    /**
     * Очистить все исключения (вернуть все чаты)
     */
    public function resetAllExclusions(int $accountId = null): int
    {
        $query = TelegramChat::excluded();
        
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $count = $query->count();
        
        $query->update([
            'is_excluded' => false,
            'excluded_at' => null,
            'excluded_reason' => null,
        ]);

        Log::info('Сброс всех исключений', [
            'account_id' => $accountId,
            'count' => $count
        ]);

        return $count;
    }

    /**
     * Автоматическое исключение неактивных чатов
     */
    public function autoExcludeInactive(int $days = 30, string $reason = 'Неактивен более 30 дней'): int
    {
        $date = now()->subDays($days);
        
        $chats = TelegramChat::active()
            ->where('last_message_date', '<', $date)
            ->orWhereNull('last_message_date')
            ->get();

        $count = 0;
        
        foreach ($chats as $chat) {
            $chat->exclude($reason);
            $count++;
        }

        Log::info('Авто-исключение неактивных чатов', [
            'days' => $days,
            'count' => $count
        ]);

        return $count;
    }
}