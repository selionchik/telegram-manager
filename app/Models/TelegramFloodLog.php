<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramFloodLog extends Model
{
    use HasFactory;

    protected $table = 'telegram_flood_logs';

    protected $fillable = [
        'chat_id',
        'account_id',
        'wait_seconds',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'wait_seconds' => 'integer',
    ];

    /**
     * Связь с чатом
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'id');
    }

    /**
     * Связь с аккаунтом
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'account_id', 'id');
    }

    /**
     * Получить чаты, которые недавно вызывали флуд
     */
    public function scopeRecent($query, int $minutes = 10)
    {
        return $query->where('occurred_at', '>=', now()->subMinutes($minutes));
    }
}