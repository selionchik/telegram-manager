<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPost extends Model
{
    use HasFactory;

    protected $table = 'telegram_posts';

    protected $fillable = [
        'chat_id',
        'message_id',
        'title',
        'content',
        'media',
        'views',
        'forwards',
        'replies_count',
        'posted_at',
        'edited',
        'edited_at',
    ];

    protected $casts = [
        'media' => 'array',
        'posted_at' => 'datetime',
        'edited' => 'boolean',
        'edited_at' => 'datetime',
        'views' => 'integer',
        'forwards' => 'integer',
        'replies_count' => 'integer',
    ];

    /**
     * Канал, в котором опубликован пост
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'id');
    }

    /**
     * Получить комментарии к посту
     */
    public function comments()
    {
        return TelegramUserComment::where('chat_id', $this->chat_id)
            ->where('post_id', $this->message_id)
            ->orderBy('date');
    }

    /**
     * Обновить статистику
     */
    public function updateStats(int $views, int $forwards, int $replies): void
    {
        $this->update([
            'views' => $views,
            'forwards' => $forwards,
            'replies_count' => $replies,
        ]);
    }

    /**
     * Получить ссылку на пост
     */
    public function getUrl(): string
    {
        if ($this->chat->username) {
            return "https://t.me/{$this->chat->username}/{$this->message_id}";
        }

        return "https://t.me/c/{$this->chat_id}/{$this->message_id}";
    }
    /**
     * Получить URL первого медиа
     */
    public function getFirstMediaUrlAttribute(): ?string
    {
        $media = $this->media ? json_decode($this->media, true) : null;

        if (!$media) {
            return null;
        }

        // Если у нас есть сохранённый файл
        if (isset($media['downloaded'])) {
            return Storage::url($media['downloaded']);
        }

        return null;
    }
}
