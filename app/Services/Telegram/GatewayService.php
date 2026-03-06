<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('telegram.gateway_url', 'http://4af690bcc2b8.vps.myjino.ru:49211');
    }

    /**
     * Получить список всех чатов
     */
    public function getDialogs(int $limit = 100): array
    {
        try {
            $params = [];
            if ($limit > 0) {
                $params['limit'] = $limit;
            }
            // если limit = 0, не передаём параметр — Telethon сам получит все

            $response = Http::timeout(30)->get("{$this->baseUrl}/api/getDialogs", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getDialogs error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error', 'dialogs' => []];
        } catch (\Exception $e) {
            Log::error('Gateway getDialogs exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Получить информацию о чате
     */
    public function getChatInfo(int $chatId): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/getChatInfo/{$chatId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getChatInfo error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error'];
        } catch (\Exception $e) {
            Log::error('Gateway getChatInfo exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Получить историю сообщений
     */
    public function getHistory(int $chatId, int $limit = 50, int $offsetId = 0, int $minId = 0): array
    {
        try {
            $params = [
                'limit' => $limit
            ];

            if ($minId > 0) {
                $params['min_id'] = $minId;
            } elseif ($offsetId > 0) {
                $params['offset_id'] = $offsetId;
            }

            $response = Http::timeout(30)->get("{$this->baseUrl}/api/getHistory/{$chatId}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getHistory error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error', 'messages' => []];
        } catch (\Exception $e) {
            Log::error('Gateway getHistory exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Получить одно сообщение
     */
    public function getMessage(int $chatId, int $messageId): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/getMessage/{$chatId}/{$messageId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getMessage error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error'];
        } catch (\Exception $e) {
            Log::error('Gateway getMessage exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Поиск сообщений по тексту
     */
    public function searchMessages(int $chatId, string $query, int $limit = 50): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/api/searchMessages/{$chatId}", [
                'query' => $query,
                'limit' => $limit
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway searchMessages error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error', 'messages' => []];
        } catch (\Exception $e) {
            Log::error('Gateway searchMessages exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Получить комментарии к посту
     */
    public function getComments(int $chatId, int $postId, int $limit = 50): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/api/getComments/{$chatId}/{$postId}", [
                'limit' => $limit
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getComments error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error', 'comments' => []];
        } catch (\Exception $e) {
            Log::error('Gateway getComments exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Скачать файл из сообщения
     */
    public function downloadFile(int $chatId, int $messageId): array
    {
        try {
            $response = Http::timeout(60)->get("{$this->baseUrl}/download/{$chatId}/{$messageId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway downloadFile error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error', 'error' => 'Download failed'];
        } catch (\Exception $e) {
            Log::error('Gateway downloadFile exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Получить информацию о текущем аккаунте
     */
    public function getMe(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/getMe");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Gateway getMe error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['status' => 'error'];
        } catch (\Exception $e) {
            Log::error('Gateway getMe exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
