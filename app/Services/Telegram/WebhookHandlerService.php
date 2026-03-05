<?php

namespace App\Services\Telegram;

use App\Models\TelegramMessage;
use App\Models\TelegramChat;
use App\Models\TelegramAccount;
use Illuminate\Support\Facades\Log;

class WebhookHandlerService
{
    protected OrderDetectorService $orderDetector;

    public function __construct(OrderDetectorService $orderDetector)
    {
        $this->orderDetector = $orderDetector;
    }

    /**
     * Обработка входящего вебхука от Telegram
     */
    public function handle(array $update): void
    {
        Log::info('Получен вебхук', ['type' => $update['_'] ?? 'unknown']);

        // Обработка сообщения
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        // Обработка отредактированного сообщения
        if (isset($update['edited_message'])) {
            $this->handleEditedMessage($update['edited_message']);
        }

        // Обработка callback query (кнопки)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        // Обработка нового комментария (reply)
        if (isset($update['message_reply'])) {
            $this->handleReply($update['message_reply']);
        }
    }

    /**
     * Обработка нового сообщения
     */
    protected function handleMessage(array $message): void
    {
        // Получаем или создаём чат
        $chat = $this->getOrCreateChat($message['chat']);
        
        // Сохраняем сообщение
        $savedMessage = $this->saveMessage($message, $chat);

        // Проверяем, не заказ ли это
        $orderData = $this->orderDetector->analyzeMessage($savedMessage);
        
        if ($orderData) {
            Log::info('Обнаружен заказ', [
                'chat' => $chat->title,
                'text' => $message['text'] ?? '',
                'order' => $orderData
            ]);
            
            // Создаём заказ
            $this->orderDetector->createOrder($savedMessage, $orderData);
            
            // TODO: Отправить уведомление менеджеру
        }
    }

    /**
     * Обработка отредактированного сообщения
     */
    protected function handleEditedMessage(array $message): void
    {
        // Находим существующее сообщение
        $existingMessage = TelegramMessage::where('message_id', $message['message_id'])
            ->where('chat_id', $message['chat']['id'])
            ->first();

        if ($existingMessage) {
            // Обновляем текст
            $existingMessage->update([
                'text' => $message['text'] ?? $existingMessage->text,
                'edited' => true,
                'edited_at' => now(),
            ]);

            Log::info('Сообщение отредактировано', [
                'message_id' => $message['message_id'],
                'new_text' => $message['text'] ?? ''
            ]);
        }
    }

    /**
     * Обработка callback query (нажатие на кнопку)
     */
    protected function handleCallbackQuery(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $messageId = $callback['message']['message_id'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;

        Log::info('Callback query', [
            'data' => $data,
            'from' => $callback['from']['id'] ?? null
        ]);

        // TODO: Обработка нажатий на кнопки (подтверждение заказа и т.д.)
    }

    /**
     * Обработка ответа на сообщение (комментарий)
     */
    protected function handleReply(array $reply): void
    {
        // TODO: Сохраняем комментарий отдельно
        Log::info('Новый комментарий', $reply);
    }

    /**
     * Получить или создать чат
     */
    protected function getOrCreateChat(array $chatData): TelegramChat
    {
        $chatId = $chatData['id'];
        $type = $this->mapChatType($chatData['type']);

        // Пытаемся найти существующий чат
        $chat = TelegramChat::find($chatId);

        if (!$chat) {
            // Если чат не найден, создаём новый
            // Нужно определить, какому аккаунту принадлежит чат
            // Пока берём первый активный аккаунт
            $account = TelegramAccount::where('status', 'connected')->first();

            $chat = TelegramChat::create([
                'id' => $chatId,
                'account_id' => $account?->id ?? 1,
                'type' => $type,
                'title' => $chatData['title'] ?? $chatData['first_name'] ?? 'Чат',
                'username' => $chatData['username'] ?? null,
                'last_message_date' => now(),
            ]);

            Log::info('Создан новый чат', ['id' => $chatId, 'type' => $type]);
        }

        return $chat;
    }

    /**
     * Сохранить сообщение в БД
     */
    protected function saveMessage(array $message, TelegramChat $chat): TelegramMessage
    {
        $fromId = $message['from']['id'] ?? null;
        $fromName = $message['from']['first_name'] ?? '';
        
        if (isset($message['from']['last_name'])) {
            $fromName .= ' ' . $message['from']['last_name'];
        }

        $savedMessage = TelegramMessage::updateOrCreate(
            [
                'chat_id' => $chat->id,
                'message_id' => $message['message_id'],
            ],
            [
                'from_id' => $fromId,
                'from_name' => trim($fromName),
                'date' => date('Y-m-d H:i:s', $message['date']),
                'text' => $message['text'] ?? '',
                'reply_to_msg_id' => $message['reply_to_message']['message_id'] ?? null,
                'has_media' => isset($message['photo']) || isset($message['document']),
                'raw_data' => json_encode($message),
            ]
        );

        // Обновляем информацию о чате
        $chat->update([
            'last_message_id' => $message['message_id'],
            'last_message_date' => now(),
            'unread_count' => $chat->unread_count + 1,
        ]);

        return $savedMessage;
    }

    /**
     * Маппинг типа чата
     */
    protected function mapChatType(string $telegramType): string
    {
        return match($telegramType) {
            'private' => 'private',
            'group' => 'group',
            'supergroup' => 'channel',
            'channel' => 'channel',
            default => 'unknown',
        };
    }
}