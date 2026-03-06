<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    use HasFactory;

    protected $table = 'telegram_chats';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = [
        'id',
        'account_id',
        'type',
        'title',
        'username',
        'photo',
        'last_message_id',
        'last_message',
        'last_message_date',
        'unread_count',
        'participants_count',
        'last_read_message_id',
        'is_excluded',
        'excluded_at',
        'excluded_reason',
        'exclude_count',
    ];

    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'last_message_id' => 'integer',
        'last_read_message_id' => 'integer',
        'unread_count' => 'integer',
        'participants_count' => 'integer',
        'last_message_date' => 'datetime',
        'excluded_at' => 'datetime',
        'photo' => 'array',
        'is_excluded' => 'boolean',
        'exclude_count' => 'integer',
    ];

    // Связи
    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'chat_id', 'id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TelegramPost::class, 'chat_id', 'id');
    }

    // Методы для непрочитанных
    public function unreadMessages()
    {
        return $this->messages()
            ->where('out', false)
            ->where('message_id', '>', $this->last_read_message_id ?? 0);
    }

    public function unreadCount(): int
    {
        return $this->unreadMessages()->count();
    }

    public function setLastRead(int $messageId): void
    {
        $this->update(['last_read_message_id' => $messageId]);
    }

    // Методы для исключения
    public function isChannel(): bool
    {
        return in_array($this->type, ['channel', 'supergroup']);
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function exclude(?string $reason = null): void
    {
        $this->update([
            'is_excluded' => true,
            'excluded_at' => now(),
            'excluded_reason' => $reason,
            'exclude_count' => $this->exclude_count + 1,
        ]);
    }

    public function include(): void
    {
        $this->update([
            'is_excluded' => false,
            'excluded_at' => null,
            'excluded_reason' => null,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_excluded', false);
    }

    public function scopeExcluded($query)
    {
        return $query->where('is_excluded', true);
    }
}