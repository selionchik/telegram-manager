<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayService
{
    protected string $baseUrl;
    protected string $token;
    
    public function __construct()
    {
        $this->baseUrl = config('telegram.gateway.url');
        $this->token = config('telegram.gateway.token');
    }

    protected function request()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->timeout(30)->baseUrl($this->baseUrl);
    }

    public function getDialogs(int $limit = 0): array
    {
        try {
            $response = $this->request()->get("/api/getDialogs", ['limit' => $limit]);
            
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

    public function getDialogsCount(): int
    {
        try {
            $response = $this->request()->get("/api/getDialogsCount");
            return $response->json()['count'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Gateway getDialogsCount exception', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function getChatInfo(int $chatId): array
    {
        try {
            $response = $this->request()->get("/api/getChatInfo/{$chatId}");
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error'];
            
        } catch (\Exception $e) {
            Log::error('Gateway getChatInfo exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getHistory(int $chatId, int $limit = 100, int $offsetId = 0, int $minId = 0): array
    {
        try {
            $params = ['limit' => $limit];
            if ($minId > 0) {
                $params['min_id'] = $minId;
            } elseif ($offsetId > 0) {
                $params['offset_id'] = $offsetId;
            }
            
            $response = $this->request()->get("/api/getHistory/{$chatId}", $params);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'messages' => []];
            
        } catch (\Exception $e) {
            Log::error('Gateway getHistory exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getMessage(int $chatId, int $messageId): array
    {
        try {
            $response = $this->request()->get("/api/getMessage/{$chatId}/{$messageId}");
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error'];
            
        } catch (\Exception $e) {
            Log::error('Gateway getMessage exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function searchMessages(int $chatId, string $query, int $limit = 50): array
    {
        try {
            $response = $this->request()->get("/api/searchMessages/{$chatId}", [
                'query' => $query,
                'limit' => $limit
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'messages' => []];
            
        } catch (\Exception $e) {
            Log::error('Gateway searchMessages exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getComments(int $chatId, int $postId, int $limit = 50): array
    {
        try {
            $response = $this->request()->get("/api/getComments/{$chatId}/{$postId}", [
                'limit' => $limit
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'comments' => []];
            
        } catch (\Exception $e) {
            Log::error('Gateway getComments exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function sendMessage(int $chatId, string $message, ?int $replyTo = null): array
    {
        try {
            $response = $this->request()->post("/api/sendMessage/{$chatId}", [
                'message' => $message,
                'reply_to' => $replyTo
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'message' => 'Failed to send'];
            
        } catch (\Exception $e) {
            Log::error('Gateway sendMessage exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function createPost(int $chatId, string $message): array
    {
        try {
            $response = $this->request()->post("/api/createPost/{$chatId}", [
                'message' => $message
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'message' => 'Failed to create post'];
            
        } catch (\Exception $e) {
            Log::error('Gateway createPost exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function downloadFile(int $chatId, int $messageId): array
    {
        try {
            $response = $this->request()->get("/download/{$chatId}/{$messageId}");
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error', 'error' => 'Download failed'];
            
        } catch (\Exception $e) {
            Log::error('Gateway downloadFile exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    public function getMe(): array
    {
        try {
            $response = $this->request()->get("/api/getMe");
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['status' => 'error'];
            
        } catch (\Exception $e) {
            Log::error('Gateway getMe exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}