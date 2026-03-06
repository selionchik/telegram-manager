<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\GatewayService;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Models\TelegramAccount;
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
    
    $lastMessage = TelegramMessage::where('chat_id', $chatId)
        ->orderBy('message_id', 'desc')
        ->first();
    
    $result = $this->gateway->getHistory(
        $chatId, 
        $limit, 
        0,
        $lastMessage?->message_id ?? 0
    );
    
    if (($result['status'] ?? '') !== 'ok') {
        Log::error('❌ Ошибка синхронизации сообщений', ['chat_id' => $chatId]);
        return 0;
    }

    $saved = 0;
    foreach ($result['messages'] as $msg) {
        try {
            $exists = TelegramMessage::where('chat_id', $chatId)
                ->where('message_id', $msg['id'])
                ->exists();
                
            if ($exists) {
                continue;
            }
            
            $message = TelegramMessage::create([
                'chat_id' => $chatId,
                'message_id' => $msg['id'],
                'from_id' => $msg['from_id'],
                'date' => date('Y-m-d H:i:s', strtotime($msg['date'])),
                'text' => $msg['text'] ?? '',
                'reply_to_msg_id' => $msg['reply_to'],
                'out' => $msg['out'] ?? false,
                'has_media' => $msg['has_media'] ?? false,
                'media_type' => $msg['media_type'] ?? 'unknown',
                'processed' => false,
            ]);
            
            $saved++;
            
            // Логируем каждое 10-е сообщение (чтобы не засорять логи)
            if ($saved % 10 == 0) {
                Log::info("📝 Сообщение {$saved} в чате {$chat->title}", [
                    'message_id' => $msg['id'],
                    'has_media' => $msg['has_media'],
                    'media_type' => $msg['media_type']
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Ошибка сохранения сообщения', [
                'message_id' => $msg['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    $chat->update(['last_scanned_at' => now()]);

    Log::info("✅ Чат {$chat->title}: добавлено {$saved} сообщений");
    
    return $saved;
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

        return view('telegram.chats.show', [
            'chat' => $chat,
            'messages' => $messages
        ]);
    }
}
