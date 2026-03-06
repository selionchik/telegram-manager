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
        'access_hash',
        'title',
        'username',
        'about',
        'last_message_id',
        'last_message',
        'last_message_date',
        'last_scanned_message_id',
        'last_parsed_message_id',
        'unread_count',
        'participants_count',
        'photo',
        'is_pinned',
        'is_excluded',
        'excluded_at',
        'excluded_reason',
        'exclude_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'access_hash' => 'integer',
        'last_message_id' => 'integer',
        'last_scanned_message_id' => 'integer',
        'last_parsed_message_id' => 'integer',
        'unread_count' => 'integer',
        'participants_count' => 'integer',
        'last_message_date' => 'datetime',
        'excluded_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'photo' => 'array',
        'is_pinned' => 'boolean',
        'is_excluded' => 'boolean',
        'exclude_count' => 'integer',
    ];

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

    public function userComments(): HasMany
    {
        return $this->hasMany(TelegramUserComment::class, 'chat_id', 'id');
    }

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

    public function scopeExcluded($query)
    {
        return $query->where('is_excluded', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_excluded', false);
    }

    public function scopeOrdered($query, string $sort = 'default')
    {
        switch ($sort) {
            case 'alphabet':
                return $query->orderBy('title');
            case 'excluded':
                return $query->orderBy('is_excluded')->orderBy('title');
            default:
                return $query->orderBy('is_excluded')->orderBy('last_message_date', 'desc');
        }
    }
}