<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Models\TelegramAccount;
use App\Models\TelegramUserComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected GatewayService $gateway;

    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Синхронизация всех чатов
     */
    public function syncDialogs(): int
    {
        Log::info('🚀 Начинаем синхронизацию чатов');

        try {
            $account = TelegramAccount::where('status', 'connected')->first();

            if (!$account) {
                Log::error('❌ Нет аккаунта для синхронизации');
                return 0;
            }

            $result = $this->gateway->getDialogs(0);

            Log::info('📦 Ответ от Gateway', ['result' => $result]);

            if (!isset($result['status']) || $result['status'] !== 'ok') {
                Log::error('❌ Неверный статус ответа', $result);
                return 0;
            }

            if (!isset($result['dialogs']) || !is_array($result['dialogs'])) {
                Log::error('❌ Нет dialogs в ответе', $result);
                return 0;
            }

            $dialogs = $result['dialogs'];
            Log::info("📦 Получено диалогов: " . count($dialogs));

            $saved = 0;

            foreach ($dialogs as $dialog) {
                try {
                    if (!isset($dialog['id'])) {
                        Log::warning('⚠️ Диалог без ID', $dialog);
                        continue;
                    }

                    // Получаем последнее сообщение для этого чата
                    $lastMessage = null;
                    if (isset($dialog['last_message_id'])) {
                        try {
                            $msgResult = $this->gateway->getMessage($dialog['id'], $dialog['last_message_id']);
                            if (($msgResult['status'] ?? '') === 'ok' && isset($msgResult['message'])) {
                                $lastMessage = $msgResult['message']['text'] ?? '[Медиа]';
                                if (mb_strlen($lastMessage) > 100) {
                                    $lastMessage = mb_substr($lastMessage, 0, 100) . '...';
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('⚠️ Не удалось получить последнее сообщение', [
                                'chat_id' => $dialog['id'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    $chat = TelegramChat::updateOrCreate(
                        ['id' => $dialog['id']],
                        [
                            'account_id' => $account->id,
                            'title' => $dialog['name'] ?? 'Без названия',
                            'type' => $dialog['type'] ?? 'unknown',
                            'username' => $dialog['username'] ?? null,
                            'unread_count' => $dialog['unread_count'] ?? 0,
                            'participants_count' => $dialog['participants_count'] ?? 0,
                            'last_message_id' => $dialog['last_message_id'] ?? null,
                            'last_message' => $lastMessage,
                            'last_message_date' => isset($dialog['last_message_date'])
                                ? date('Y-m-d H:i:s', strtotime($dialog['last_message_date']))
                                : null,
                            'photo' => isset($dialog['photo']) ? json_encode($dialog['photo']) : null,
                        ]
                    );
                    $saved++;

                    Log::info("✅ Сохранён чат: {$chat->id} - {$chat->title}");
                } catch (\Exception $e) {
                    Log::error('❌ Ошибка сохранения чата', [
                        'chat_id' => $dialog['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info("✅ Сохранено чатов: {$saved}");
            return $saved;
        } catch (\Exception $e) {
            Log::error('💥 Критическая ошибка в syncDialogs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Синхронизация сообщений для конкретного чата
     */

    public function syncMessages(int $chatId, int $limit = 100): int
    {
        $chat = TelegramChat::findOrFail($chatId);

        // Получаем последние сообщения
        $result = $this->gateway->getHistory($chatId, $limit, 0, 0);

        if (($result['status'] ?? '') !== 'ok') {
            Log::error('❌ Ошибка синхронизации сообщений', ['chat_id' => $chatId]);
            return 0;
        }

        $saved = 0;
        foreach ($result['messages'] as $msg) {
            try {
                // Сохраняем/обновляем сообщение
                $message = TelegramMessage::updateOrCreate(
                    ['chat_id' => $chatId, 'message_id' => $msg['id']],
                    [
                        'from_id' => $msg['from_id'],
                        'date' => date('Y-m-d H:i:s', strtotime($msg['date'])),
                        'text' => $msg['text'] ?? '',
                        'reply_to_msg_id' => $msg['reply_to'],
                        'out' => $msg['out'] ?? false,
                        'has_media' => $msg['has_media'] ?? false,
                        'media_type' => $msg['media_type'] ?? 'unknown',
                        'processed' => false,
                    ]
                );

                // Если это канал и есть комментарии — загружаем их
                if ($chat->type === 'channel' && ($msg['replies_count'] ?? 0) > 0) {
                    $this->syncComments($chatId, $msg['id']);
                }
            } catch (\Exception $e) {
                Log::error('❌ Ошибка сохранения сообщения', ['message_id' => $msg['id']]);
            }
        }

        $chat->update(['last_scanned_at' => now()]);
        return $saved;
    }

    /**
     * Загрузить комментарии к посту
     */
    protected function syncComments(int $chatId, int $postId)
    {
        $result = $this->gateway->getComments($chatId, $postId);

        if (($result['status'] ?? '') !== 'ok') {
            return;
        }

        $chat = TelegramChat::find($chatId);

        foreach ($result['comments'] as $comment) {
            TelegramUserComment::updateOrCreate(
                [
                    'user_id' => $comment['from_id'],
                    'comment_id' => $comment['id']
                ],
                [
                    'user_name' => $comment['user_name'] ?? 'Пользователь',
                    'chat_id' => $chatId,
                    'chat_title' => $chat->title ?? '',
                    'post_id' => $postId,
                    'text' => $comment['text'],
                    'date' => date('Y-m-d H:i:s', strtotime($comment['date'])),
                    'processed' => false,
                ]
            );
        }

        Log::info("✅ Загружено комментариев для поста {$postId}: " . count($result['comments']));
    }

    /**
     * Отображение списка чатов
     */
    public function index(Request $request)
    {
        $sort = $request->get('sort', 'default');

        $query = TelegramChat::query();

        if ($sort !== 'excluded') {
            $query->where('is_excluded', false);
        }

        switch ($sort) {
            case 'alphabet':
                $query->orderBy('title');
                break;
            case 'excluded':
                $query->where('is_excluded', true)->orderBy('excluded_at', 'desc');
                break;
            default:
                $query->orderBy('last_message_date', 'desc');
        }

        $chats = $query->paginate(50);

        return view('telegram.chats.index', [
            'chats' => $chats,
            'currentSort' => $sort,
            'sortOptions' => ['default', 'alphabet', 'excluded']
        ]);
    }

    /**
     * Просмотр конкретного чата
     */
    public function show(int $id)
    {
        $chat = TelegramChat::with('account')->findOrFail($id);

        $messages = TelegramMessage::where('chat_id', $id)
            ->orderBy('date', 'desc')
            ->paginate(50);
        // dd($messages);
        // Добавляем количество комментариев к каждому сообщению
        $commentCounts = TelegramUserComment::where('chat_id', $id)
            ->whereIn('post_id', $messages->pluck('message_id'))
            ->groupBy('post_id')
            ->selectRaw('post_id, count(*) as count')
            ->pluck('count', 'post_id');
        $loadedComments = TelegramUserComment::where('chat_id', $id)
            ->whereIn('post_id', $messages->pluck('message_id'))
            ->orderBy('date')
            ->get()
            ->groupBy('post_id');

        return view('telegram.chats.show', [
            'chat' => $chat,
            'messages' => $messages,
            'commentCounts' => $commentCounts,
            'loadedComments' => $loadedComments
        ]);
    }

    /**
     * Получить комментарии к посту (AJAX)
     */
    public function getPostComments(int $chatId, int $postId)
    {
        $result = $this->gateway->getComments($chatId, $postId);

        if (($result['status'] ?? '') !== 'ok') {
            return response()->json(['error' => 'Failed to load comments'], 500);
        }

        // Получаем название чата для сохранения
        $chat = TelegramChat::find($chatId);
        $chatTitle = $chat ? $chat->title : 'Unknown';

        // Сохраняем комментарии в БД
        foreach ($result['comments'] as $comment) {
            TelegramUserComment::updateOrCreate(
                [
                    'user_id' => $comment['from_id'],
                    'comment_id' => $comment['id']
                ],
                [
                    'user_name' => '', // TODO: получить имя пользователя
                    'chat_id' => $chatId,
                    'chat_title' => $chatTitle,
                    'post_id' => $postId,
                    'text' => $comment['text'],
                    'date' => date('Y-m-d H:i:s', strtotime($comment['date'])),
                    'processed' => false,
                ]
            );
        }

        // Возвращаем комментарии для отображения
        return response()->json([
            'comments' => $result['comments']
        ]);
    }

    /**
     * Синхронизация чатов с колбэком для прогресса
     */
    public function syncDialogsWithProgress(callable $progressCallback): int
    {
        Log::info('🚀 Начинаем синхронизацию чатов с прогрессом');

        try {
            $account = TelegramAccount::where('status', 'connected')->first();

            if (!$account) {
                Log::error('❌ Нет аккаунта для синхронизации');
                return 0;
            }

            $result = $this->gateway->getDialogs(0); // получаем все чаты

            if (!isset($result['status']) || $result['status'] !== 'ok') {
                Log::error('❌ Неверный статус ответа', $result);
                return 0;
            }

            if (!isset($result['dialogs']) || !is_array($result['dialogs'])) {
                Log::error('❌ Нет dialogs в ответе', $result);
                return 0;
            }

            $dialogs = $result['dialogs'];
            $total = count($dialogs);
            Log::info("📦 Получено диалогов: {$total}");

            $saved = 0;

            foreach ($dialogs as $index => $dialog) {
                try {
                    if (!isset($dialog['id'])) {
                        continue;
                    }

                    // Получаем последнее сообщение
                    $lastMessage = null;
                    if (isset($dialog['last_message_id'])) {
                        try {
                            $msgResult = $this->gateway->getMessage($dialog['id'], $dialog['last_message_id']);
                            if (($msgResult['status'] ?? '') === 'ok' && isset($msgResult['message'])) {
                                $lastMessage = $msgResult['message']['text'] ?? '[Медиа]';
                                if (mb_strlen($lastMessage) > 100) {
                                    $lastMessage = mb_substr($lastMessage, 0, 100) . '...';
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('⚠️ Не удалось получить последнее сообщение', [
                                'chat_id' => $dialog['id'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    TelegramChat::updateOrCreate(
                        ['id' => $dialog['id']],
                        [
                            'account_id' => $account->id,
                            'title' => $dialog['name'] ?? 'Без названия',
                            'type' => $dialog['type'] ?? 'unknown',
                            'username' => $dialog['username'] ?? null,
                            'unread_count' => $dialog['unread_count'] ?? 0,
                            'participants_count' => $dialog['participants_count'] ?? 0,
                            'last_message_id' => $dialog['last_message_id'] ?? null,
                            'last_message' => $lastMessage,
                            'last_message_date' => isset($dialog['last_message_date'])
                                ? date('Y-m-d H:i:s', strtotime($dialog['last_message_date']))
                                : null,
                            'photo' => isset($dialog['photo']) ? json_encode($dialog['photo']) : null,
                        ]
                    );
                    $saved++;

                    // Вызываем callback для прогресса
                    $progressCallback($saved, $total);
                } catch (\Exception $e) {
                    Log::error('❌ Ошибка сохранения чата', [
                        'chat_id' => $dialog['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info("✅ Сохранено чатов: {$saved}");
            return $saved;
        } catch (\Exception $e) {
            Log::error('💥 Критическая ошибка в syncDialogs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}
