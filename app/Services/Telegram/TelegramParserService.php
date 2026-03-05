<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Models\TelegramPost;
use App\Models\TelegramUserComment;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramParserService
{
    private MultiAccountService $multiAccount;

    protected array $downloadedFiles = [];

    public function __construct(MultiAccountService $multiAccount)
    {
        $this->multiAccount = $multiAccount;
    }

    /**
     * Получить все диалоги (чаты) для аккаунта
     */
    /**
     * Получить все диалоги (чаты) для аккаунта
     */
    public function fetchDialogs(string $accountName): array
    {
        $madeline = $this->multiAccount->getAccount($accountName);

        if (!$madeline) {
            Log::error("Аккаунт {$accountName} недоступен");
            return [];
        }

        try {
            Log::info("ШАГ 1: Получаем диалоги через messages->getDialogs()");
            $dialogsResponse = $madeline->messages->getDialogs(limit: 100);

            Log::info("ШАГ 2: Структура ответа", [
                'keys' => array_keys($dialogsResponse),
                'has_dialogs' => isset($dialogsResponse['dialogs']),
                'dialogs_count' => isset($dialogsResponse['dialogs']) ? count($dialogsResponse['dialogs']) : 0
            ]);

            $dialogList = $dialogsResponse['dialogs'] ?? [];

            Log::info("ШАГ 3: Найдено диалогов в ответе: " . count($dialogList));

            if (empty($dialogList)) {
                Log::warning("Диалоги не найдены в ответе", ['response_sample' => json_encode($dialogsResponse, JSON_PRETTY_PRINT)]);
                return [];
            }

            $chats = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($dialogList as $index => $dialog) {
                Log::info("ШАГ 4: Обработка диалога #{$index}", ['dialog_keys' => array_keys($dialog)]);

                $chat = $this->processDialog($dialog, $accountName, $madeline);

                if ($chat) {
                    $successCount++;
                    $chats[] = $chat;
                    Log::info("✓ Диалог #{$index} успешно сохранён", ['chat_id' => $chat->id, 'title' => $chat->title]);
                } else {
                    $failCount++;
                    Log::warning("✗ Диалог #{$index} не сохранён");
                }
            }

            Log::info("ШАГ 5: Итог", [
                'всего_диалогов' => count($dialogList),
                'успешно' => $successCount,
                'ошибок' => $failCount,
                'сохранено_чатов' => count($chats)
            ]);

            return $chats;
        } catch (\Exception $e) {
            Log::error("Ошибка получения диалогов для {$accountName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Обработать один диалог и сохранить в БД
     */
    private function processDialog(array $dialog, string $accountName, $madeline): ?TelegramChat
    {
        try {
            Log::info("  processDialog: начало обработки", ['dialog' => json_encode($dialog)]);

            $peerId = null;
            $peerType = null;

            if (is_numeric($dialog['peer'] ?? null)) {
                $peerId = $dialog['peer'];
                $peerType = $this->guessPeerType($peerId);
                Log::info("  processDialog: числовой peer", ['peerId' => $peerId, 'type' => $peerType]);
            } elseif (is_array($dialog['peer'] ?? null)) {
                $peerId = $this->extractChatId($dialog['peer']);
                $peerType = $this->getChatType($dialog['peer']['_'] ?? '');
                Log::info("  processDialog: массив peer", ['peerId' => $peerId, 'type' => $peerType, 'peer_array' => $dialog['peer']]);
            } else {
                Log::warning("  processDialog: неизвестный формат peer");
                return null;
            }

            if (!$peerId) {
                Log::warning("  processDialog: peerId не определён");
                return null;
            }

            Log::info("  processDialog: получаем информацию о чате", ['peerId' => $peerId]);
            $fullChat = $this->getFullChatInfo($madeline, $peerId);

            Log::info("  processDialog: информация о чате получена", ['fullChat_keys' => array_keys($fullChat)]);

            $account = TelegramAccount::where('name', $accountName)->first();

            if (!$account) {
                Log::error("  processDialog: аккаунт не найден");
                return null;
            }

            $chatData = [
                'account_id' => $account->id,
                'type' => $peerType,
                'access_hash' => $fullChat['access_hash'] ?? null,
                'title' => $this->extractTitle($fullChat, $peerType),
                'username' => $fullChat['username'] ?? null,
                'about' => $fullChat['about'] ?? null,
                'participants_count' => $fullChat['participants_count'] ?? 0,
                'photo' => isset($fullChat['photo']) ? json_encode($fullChat['photo']) : null,
                'last_message_id' => $dialog['top_message'] ?? null,
                'last_message_date' => isset($dialog['top_message']) ? now() : null,
                'unread_count' => $dialog['unread_count'] ?? 0,
                'is_pinned' => $dialog['pinned'] ?? false,
                'is_excluded' => false,
                'exclude_count' => 0,
            ];

            Log::info("  processDialog: данные для сохранения", $chatData);

            $chat = TelegramChat::updateOrCreate(
                ['id' => $peerId],
                $chatData
            );

            Log::info("  processDialog: чат сохранён", ['id' => $chat->id, 'title' => $chat->title]);

            return $chat;
        } catch (\Exception $e) {
            Log::error("  processDialog: ошибка: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Определить тип чата по ID
     */
    private function guessPeerType(int $peerId): string
    {
        // В Telegram: 
        // положительные числа до 2^31 - личные чаты
        // отрицательные числа - группы/каналы
        return $peerId > 0 ? 'private' : 'channel';
    }

    /**
     * Получить полную информацию о чате используя универсальный метод getInfo
     */
    private function getFullChatInfo($madeline, int $peerId): array
    {
        try {
            Log::info("    getFullChatInfo: запрос для peerId {$peerId}");
            $info = $madeline->getInfo($peerId);
            Log::info("    getFullChatInfo: ответ получен", ['info_keys' => array_keys($info)]);

            if (isset($info['Chat'])) {
                Log::info("    getFullChatInfo: найден Chat");
                return $info['Chat'];
            } elseif (isset($info['User'])) {
                Log::info("    getFullChatInfo: найден User");
                return $info['User'];
            } elseif (isset($info['Channel'])) {
                Log::info("    getFullChatInfo: найден Channel");
                return $info['Channel'];
            }

            Log::warning("    getFullChatInfo: неизвестная структура", ['info' => json_encode($info)]);
            return [];
        } catch (\Exception $e) {
            Log::warning("    getFullChatInfo: ошибка для peerId {$peerId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Извлечь название чата
     */
    private function extractTitle(array $chatInfo, string $chatType): string
    {
        if ($chatType === 'private') {
            $firstName = $chatInfo['first_name'] ?? '';
            $lastName = $chatInfo['last_name'] ?? '';
            return trim($firstName . ' ' . $lastName) ?: 'Пользователь';
        }

        return $chatInfo['title'] ?? 'Без названия';
    }

    /**
     * Извлечь ID чата из peer массива
     */
    private function extractChatId(array $peer): ?int
    {
        return $peer['channel_id'] ?? $peer['chat_id'] ?? $peer['user_id'] ?? null;
    }

    /**
     * Получить тип чата из строки peer типа
     */
    private function getChatType(string $peerType): string
    {
        return match ($peerType) {
            'peerUser' => 'private',
            'peerChat' => 'group',
            'peerChannel' => 'channel',
            default => 'unknown',
        };
    }

/**
 * Получить историю сообщений чата
 */
public function fetchMessages(TelegramChat $chat, int $limit = 50): array
{
    // Если чат исключён, не парсим
    if ($chat->is_excluded) {
        Log::info('Чат исключён из парсинга', ['chat_id' => $chat->id, 'title' => $chat->title]);
        return [];
    }

    $madeline = $this->multiAccount->getAccount($chat->account->name);
    
    if (!$madeline) {
        Log::error("Аккаунт {$chat->account->name} недоступен для получения сообщений");
        return [];
    }

    try {
        $peer = $chat->id; // Используем просто ID
        
        // Получаем ТОЛЬКО новые сообщения (после last_parsed_message_id)
        $history = $madeline->messages->getHistory(
            peer: $peer,
            limit: $limit,
            offset_id: $chat->last_parsed_message_id ?? 0,
            offset_date: 0,
            add_offset: 0,
            max_id: 0,
            min_id: 0,
            hash: 0
        );

        $messages = [];
        $newLastMessageId = $chat->last_parsed_message_id;
        
        foreach ($history['messages'] as $msg) {
            if ($this->isServiceMessage($msg)) {
                continue;
            }

            // Пропускаем уже обработанные
            if ($msg['id'] <= ($chat->last_parsed_message_id ?? 0)) {
                continue;
            }

            $message = $this->processMessage($msg, $chat);
            if ($message) {
                $messages[] = $message;
                
                // Запоминаем самое новое сообщение
                if ($msg['id'] > ($newLastMessageId ?? 0)) {
                    $newLastMessageId = $msg['id'];
                }
                
                // Если это комментарий в канале, сохраняем отдельно
                if ($chat->isChannel() && isset($msg['reply_to'])) {
                    $this->processComment($msg, $chat);
                }
            }
        }

        // Обновляем ID последнего обработанного сообщения
        if ($newLastMessageId && $newLastMessageId > ($chat->last_parsed_message_id ?? 0)) {
            $chat->update([
                'last_parsed_message_id' => $newLastMessageId,
                'last_scanned_at' => now(),
            ]);
        }

        // Увеличиваем счётчик обработанных сообщений
        $chat->account->incrementParsedCount(count($messages));

        Log::info("Получено новых сообщений для чата {$chat->title}: " . count($messages));
        
        return $messages;

    } catch (\Exception $e) {
        Log::error("Ошибка получения сообщений чата {$chat->id}: " . $e->getMessage());
        return [];
    }
}

    /**
     * Построить peer для API запроса
     */
    /**
     * Построить peer для API запроса - УПРОЩЁННАЯ ВЕРСИЯ
     */
    private function buildPeer(TelegramChat $chat)
    {
        // MadelineProto принимает просто числовой ID!
        return $chat->id;
    }

    /**
     * Обработать одно сообщение
     */
/**
 * Обработать одно сообщение
 */
private function processMessage(array $msg, TelegramChat $chat): ?TelegramMessage
{
    try {
        $fromId = $this->extractSenderId($msg);
        $fromName = $this->extractSenderName($msg);
        
        $mediaInfo = null;
        $downloadedPath = null;
        
        if (isset($msg['media'])) {
            $mediaInfo = $msg['media'];
            
            // Сохраняем сообщение сначала без файла
            $message = TelegramMessage::updateOrCreate(
                [
                    'chat_id' => $chat->id,
                    'message_id' => $msg['id'],
                ],
                [
                    'from_id' => $fromId,
                    'from_name' => $fromName,
                    'date' => date('Y-m-d H:i:s', $msg['date']),
                    'text' => $msg['message'] ?? '',
                    'reply_to_msg_id' => $msg['reply_to']['reply_to_msg_id'] ?? null,
                    'out' => $msg['out'] ?? false,
                    'has_media' => true,
                    'media_info' => json_encode($mediaInfo),
                    'processed' => false,
                ]
            );
            
            // Отправляем скачивание в очередь (НЕ БЛОКИРУЕМ ОСНОВНОЙ ПОТОК)
            \App\Jobs\DownloadTelegramFile::dispatch($mediaInfo, $chat, $message)
                ->onQueue('downloads')
                ->delay(now()->addSeconds(rand(1, 5))); // Небольшая задержка между задачами
            
            return $message;
            
        } else {
            // Сообщение без медиа
            return TelegramMessage::updateOrCreate(
                [
                    'chat_id' => $chat->id,
                    'message_id' => $msg['id'],
                ],
                [
                    'from_id' => $fromId,
                    'from_name' => $fromName,
                    'date' => date('Y-m-d H:i:s', $msg['date']),
                    'text' => $msg['message'] ?? '',
                    'reply_to_msg_id' => $msg['reply_to']['reply_to_msg_id'] ?? null,
                    'out' => $msg['out'] ?? false,
                    'has_media' => false,
                    'processed' => false,
                ]
            );
        }

    } catch (\Exception $e) {
        Log::error("Ошибка обработки сообщения: " . $e->getMessage());
        return null;
    }
}

    /**
     * Обработать комментарий (для каналов)
     */
    private function processComment(array $msg, TelegramChat $chat): ?TelegramUserComment
    {
        try {
            $postId = $msg['reply_to']['reply_to_msg_id'] ?? null;

            if (!$postId) {
                return null;
            }

            $fromId = $this->extractSenderId($msg);
            $fromName = $this->extractSenderName($msg);

            return TelegramUserComment::updateOrCreate(
                [
                    'user_id' => $fromId,
                    'comment_id' => $msg['id'],
                ],
                [
                    'user_name' => $fromName,
                    'chat_id' => $chat->id,
                    'chat_title' => $chat->title,
                    'post_id' => $postId,
                    'text' => $msg['message'] ?? '',
                    'date' => date('Y-m-d H:i:s', $msg['date']),
                    'processed' => false,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Ошибка обработки комментария: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отправить сообщение
     */
    public function sendMessage(TelegramChat $chat, string $message, ?int $replyToId = null): bool
    {
        $madeline = $this->multiAccount->getAccount($chat->account->name);

        if (!$madeline) {
            return false;
        }

        try {
            $peer = $this->buildPeer($chat);

            $result = $madeline->messages->sendMessage(
                peer: $peer,
                message: $message,
                reply_to_msg_id: $replyToId
            );

            // Сохраняем отправленное сообщение
            $this->processMessage($result, $chat);

            return true;
        } catch (\Exception $e) {
            Log::error("Ошибка отправки сообщения: " . $e->getMessage());

            if (str_contains($e->getMessage(), 'FLOOD_WAIT')) {
                // Будет обработано в очереди
                throw $e;
            }

            return false;
        }
    }

    /**
     * Создать пост в канале
     */
    public function createPost(TelegramChat $chat, string $content, ?array $media = null): bool
    {
        if (!$chat->isChannel()) {
            Log::error("Попытка создать пост не в канале");
            return false;
        }

        $madeline = $this->multiAccount->getAccount($chat->account->name);

        if (!$madeline) {
            return false;
        }

        try {
            $peer = $this->buildPeer($chat);

            $params = [
                'peer' => $peer,
                'message' => $content,
            ];

            // TODO: добавить загрузку медиа, если нужно
            // if ($media) { ... }

            $result = $madeline->messages->sendMessage(...$params);

            // Сохраняем как пост
            TelegramPost::create([
                'chat_id' => $chat->id,
                'message_id' => $result['id'],
                'content' => $content,
                'media' => $media ? json_encode($media) : null,
                'posted_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Ошибка создания поста: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Парсинг только активных чатов (не исключённых)
     */
    public function fetchAllActiveMessages(int $accountId = null, int $limitPerChat = 50): array
    {
        $query = TelegramChat::active();

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $chats = $query->get();
        $allMessages = [];

        foreach ($chats as $chat) {
            $messages = $this->fetchMessages($chat, $limitPerChat);
            $allMessages = array_merge($allMessages, $messages);
        }

        return $allMessages;
    }

    /**
     * Извлечь ID отправителя
     */
    private function extractSenderId(array $msg): ?int
    {
        if (isset($msg['from_id'])) {
            $from = $msg['from_id'];
            return $from['user_id'] ?? $from['channel_id'] ?? null;
        }
        return null;
    }

    /**
     * Извлечь имя отправителя
     */
    private function extractSenderName(array $msg): ?string
    {
        // TODO: можно добавить позже, если нужно
        return null;
    }

    /**
     * Проверить, является ли сообщение служебным
     */
    private function isServiceMessage(array $msg): bool
    {
        return isset($msg['action']) || $msg['_'] === 'messageService';
    }

    /**
     * Скачать файл из Telegram
     */
    private function downloadFile(array $media, TelegramChat $chat): ?string
    {
        try {
            // Определяем тип медиа
            if (isset($media['photo'])) {
                $fileInfo = $media['photo'];
            } elseif (isset($media['document'])) {
                $fileInfo = $media['document'];
            } else {
                return null;
            }

            // Получаем ID файла
            $fileId = $fileInfo['id'] ?? null;
            if (!$fileId) {
                return null;
            }

            // Проверяем, не скачивали ли уже
            if (isset($this->downloadedFiles[$fileId])) {
                return $this->downloadedFiles[$fileId];
            }

            // Формируем путь для сохранения
            $datePath = now()->format('Y/m/d');
            $fileName = $fileId . '_' . time() . '.jpg';
            $relativePath = "telegram/{$chat->account->name}/{$datePath}/{$fileName}";
            $fullPath = storage_path("app/public/{$relativePath}");

            // Создаём папку
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            // Скачиваем файл
            $this->downloadFileToPath($madeline ?? null, $fileInfo, $fullPath);

            // Сохраняем в кэш
            $this->downloadedFiles[$fileId] = $relativePath;

            return $relativePath;
        } catch (\Exception $e) {
            Log::error("Ошибка скачивания файла: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Скачать файл по пути
     */
    private function downloadFileToPath($madeline, array $fileInfo, string $path): void
    {
        // Получаем экземпляр MadelineProto если не передан
        if (!$madeline) {
            $madeline = $this->multiAccount->getAvailableAccount();
        }

        try {
            // ИСПРАВЛЕНО: downloadToFile вместо downloadTo
            $file = $madeline->downloadToFile($fileInfo, $path);

            Log::info("Файл сохранён: {$path}", ['size' => filesize($path) ?? 0]);
        } catch (\Exception $e) {
            Log::error("Ошибка скачивания файла: " . $e->getMessage());
            throw $e;
        }
    }

/**
 * Сбросить указатель последнего сообщения (для перепарсинга)
 */
public function resetChatPointer(TelegramChat $chat): void
{
    $chat->update([
        'last_parsed_message_id' => null,
        'last_scanned_message_id' => null,
    ]);
    
    Log::info("Сброшен указатель сообщений для чата {$chat->title}");
}    
}
