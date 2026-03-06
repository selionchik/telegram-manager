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
        'media_type',
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
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'id');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_msg_id', 'message_id')
            ->where('chat_id', $this->chat_id);
    }

    public function getDisplayUrlAttribute(): ?string
    {
        if (!$this->downloaded_file) {
            return null;
        }
        
        return Storage::url($this->downloaded_file);
    }


    public function getMediaIconAttribute(): string
    {
        return match($this->media_type) {
            'photo' => 'bi-image',
            'video' => 'bi-film',
            'audio' => 'bi-music-note',
            'document' => 'bi-file-text',
            default => 'bi-file'
        };
    }

    public function getMediaTypeTextAttribute(): string
    {
        return match($this->media_type) {
            'photo' => 'Фото',
            'video' => 'Видео',
            'audio' => 'Аудио',
            'document' => 'Документ',
            default => 'Файл'
        };
    }

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

    public function markAsProcessed(): void
    {
        $this->update(['processed' => true]);
    }
}