<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUserComment extends Model
{
    use HasFactory;

    protected $table = 'telegram_user_comments';

    protected $fillable = [
        'user_id',
        'user_name',
        'chat_id',
        'chat_title',
        'post_id',
        'comment_id',
        'text',
        'date',
    'processed',
    'processed_at',
    'processed_by',        
    ];

    protected $casts = [
        'date' => 'datetime',
    'processed' => 'boolean',
    'processed_at' => 'datetime',        
    ];


    /**
     * Чат, в котором оставлен комментарий
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'id');
    }

    /**
     * Пост, к которому оставлен комментарий
     */
    public function post()
    {
        return $this->belongsTo(TelegramPost::class, 'post_id', 'message_id')
            ->where('chat_id', $this->chat_id);
    }

public function processor()
{
    return $this->belongsTo(User::class, 'processed_by');
}    

    /**
     * Получить все комментарии пользователя
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->orderBy('date', 'desc');
    }

    /**
     * Получить комментарии за период
     */
    public function scopeInPeriod($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Поиск по тексту комментария
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('text', 'like', "%{$search}%");
    }

    public function markAsProcessed(int $userId = null): void
{
    $this->update([
        'processed' => true,
        'processed_at' => now(),
        'processed_by' => $userId,
    ]);
}

public function scopeUnprocessed($query)
{
    return $query->where('processed', false);
}

public function scopeProcessed($query)
{
    return $query->where('processed', true);
}
}