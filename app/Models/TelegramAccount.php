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
        'phone',
        'status',
        'last_error',
        'last_connected_at',
        'messages_parsed_today',
        'settings',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'messages_parsed_today' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Связь с чатами аккаунта
     */
    public function chats(): HasMany
    {
        return $this->hasMany(TelegramChat::class, 'account_id');
    }

    /**
     * Проверка, может ли аккаунт выполнять парсинг - БЕЗ ЖЁСТКОГО ЛИМИТА
     */
    public function canParse(): bool
    {
        return $this->status === 'connected'; // Убрали лимит, полагаемся на обработку FLOOD_WAIT
    }

    /**
     * Увеличить счётчик обработанных сообщений
     */
    public function incrementParsedCount(int $count = 1): void
    {
        $this->increment('messages_parsed_today', $count);
    }

    /**
     * Сбросить дневной счётчик (вызывать по крону в полночь)
     */
    public function resetDailyCounter(): void
    {
        $this->update(['messages_parsed_today' => 0]);
    }

    /**
     * Получить настройки для MadelineProto
     */
    public function getMadelineSettings(): array
    {
        return [
            'app_info' => [
                'api_id' => config("telegram.accounts.{$this->name}.api_id"),
                'api_hash' => config("telegram.accounts.{$this->name}.api_hash"),
            ],
            'logger' => [
                'logger' => 3, // FILE_LOGGER
                'logger_level' => 2, // WARNING
                'logger_param' => storage_path("logs/telegram_{$this->name}.log"),
            ],
            'serialization' => [
                'serialization_interval' => 300,
            ],
        ];
    }
}
