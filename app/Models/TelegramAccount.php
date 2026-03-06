<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramAccount extends Model
{
    use HasFactory;

    protected $table = 'telegram_accounts';

    protected $fillable = [
        'name',
        'tg_id',
        'first_name',
        'last_name',
        'username',
        'phone',
        'status',
        'last_error',
        'last_connected_at',
        'messages_parsed_today',
        'settings',
    ];

    protected $casts = [
        'tg_id' => 'integer',
        'last_connected_at' => 'datetime',
        'messages_parsed_today' => 'integer',
        'settings' => 'array',
    ];

    public function chats(): HasMany
    {
        return $this->hasMany(TelegramChat::class, 'account_id');
    }

    public function canParse(): bool
    {
        return $this->status === 'connected';
    }

    public function incrementParsedCount(int $count = 1): void
    {
        $this->messages_parsed_today += $count;
        $this->save();
    }
}