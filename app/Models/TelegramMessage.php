<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TelegramMessage extends Model
{
    use HasFactory;

    protected $table = 'telegram_messages';

    protected $fillable = [
        'chat_id',
        'message_id',
        'from_id',
        'from_name',
        'date',
        'text',
        'reply_to_msg_id',
        'forward_from_chat_id',
        'forward_from_message_id',
        'out',
        'has_media',
        'media_info',
        'downloaded_file',
        'thumbnail_path',
        'file_url_clicked',
        'file_downloaded_at',
        'raw_data',
        'processed',
    ];

    protected $casts = [
        'date' => 'datetime',
        'out' => 'boolean',
        'has_media' => 'boolean',
        'processed' => 'boolean',
        'file_url_clicked' => 'boolean',
        'file_downloaded_at' => 'datetime',
        'media_info' => 'array',
        'raw_data' => 'array',
        'replies_count' => 'integer',
    ];

    /**
     * Связь с чатом - ЭТОТ МЕТОД ДОЛЖЕН БЫТЬ!
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'id');
    }

    /**
     * Сообщение, на которое отвечают
     */
    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_msg_id', 'message_id')
            ->where('chat_id', $this->chat_id);
    }

/**
 * Получить URL для отображения (плейсхолдер или картинка)
 */
/**
 * Получить URL для отображения (плейсхолдер или картинка)
 */
public function getDisplayUrlAttribute(): ?string
{
    if (!$this->downloaded_file) {
        return null;
    }
    
    // Создаём именованный маршрут для файлов
    return route('telegram.file', ['path' => $this->downloaded_file]);
}

    /**
     * Получить путь к превью (если есть)
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::url($this->thumbnail_path);
        }
        return null;
    }

    /**
     * Проверка, можно ли скачать файл
     */
    public function canDownload(): bool
    {
        if ($this->downloaded_file) {
            return false;
        }

        $mediaInfo = $this->media_info ? json_decode($this->media_info, true) : null;
        if ($mediaInfo && isset($mediaInfo['document']['size'])) {
            if ($mediaInfo['document']['size'] > 20 * 1024 * 1024) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получить размер файла (для отображения)
     */
    public function getFileSizeAttribute(): ?string
    {
        $mediaInfo = $this->media_info ? json_decode($this->media_info, true) : null;

        if ($mediaInfo && isset($mediaInfo['document']['size'])) {
            $bytes = $mediaInfo['document']['size'];
            if ($bytes < 1024) return $bytes . ' B';
            if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
            return round($bytes / 1048576, 1) . ' MB';
        }

        return null;
    }

    /**
     * Проверка, является ли сообщение заказом
     */
    public function isOrder(): bool
    {
        if (empty($this->text)) {
            return false;
        }

        $patterns = [
            '/арт[.\s]*?(\d+)/i',
            '/(\d+)[.\s]*?(метр|м|м\.)/ui',
            '/(красн|син|бел|черн|зелен|желт|голуб|розов|фиолет|коричн)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отметить как обработанное
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed' => true]);
    }

    /**
     * Получить ссылку на сообщение
     */
    public function getUrl(): ?string
    {
        if ($this->chat->username) {
            return "https://t.me/{$this->chat->username}/{$this->message_id}";
        }

        if ($this->chat->isPrivate()) {
            return null;
        }

        return "https://t.me/c/{$this->chat_id}/{$this->message_id}";
    }

    /**
     * Получить тип медиа
     */
    public function getMediaTypeAttribute(): string
    {
        $mediaInfo = $this->media_info ? json_decode($this->media_info, true) : null;

        if (!$mediaInfo) {
            return 'unknown';
        }

        if (isset($mediaInfo['photo'])) {
            return 'photo';
        }

        if (isset($mediaInfo['video'])) {
            return 'video';
        }

        if (isset($mediaInfo['audio'])) {
            return 'audio';
        }

        if (isset($mediaInfo['document'])) {
            $doc = $mediaInfo['document'];
            if (isset($doc['mime_type'])) {
                if (str_contains($doc['mime_type'], 'video')) {
                    return 'video';
                }
                if (str_contains($doc['mime_type'], 'audio')) {
                    return 'audio';
                }
                if (str_contains($doc['mime_type'], 'image')) {
                    return 'photo';
                }
            }
            return 'document';
        }

        return 'unknown';
    }

    /**
     * Получить иконку для типа медиа
     */
    public function getMediaIconAttribute(): string
    {
        return match ($this->media_type) {
            'photo' => 'bi-image',
            'video' => 'bi-film',
            'audio' => 'bi-music-note',
            'document' => 'bi-file-text',
            default => 'bi-file'
        };
    }

    /**
     * Получить человеко-читаемый тип
     */
    public function getMediaTypeTextAttribute(): string
    {
        return match ($this->media_type) {
            'photo' => 'Фото',
            'video' => 'Видео',
            'audio' => 'Аудио',
            'document' => 'Документ',
            default => 'Файл'
        };
    }
}
