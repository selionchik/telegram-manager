<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramProxy extends Model
{
    protected $table = 'telegram_proxies';

    protected $fillable = [
        'server',
        'port',
        'secret',
        'type',
        'source',
        'is_active',
        'last_checked_at',
        'last_used_at',
        'fail_count',
        'response_time',      // новое поле
        'success_rate',       // новое поле
        'last_speed_rating',  // новое поле
        'cdn_capable',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'fail_count' => 'integer',
        'response_time' => 'float',
        'success_rate' => 'float',
        'cdn_capable' => 'boolean',
    ];

    /**
     * Получить случайный активный прокси (с учётом скорости)
     */
    public static function getRandom(): ?self
    {
        // Сначала пробуем быстрые Fake-TLS
        $fastProxies = self::where('type', 'fake_tls')
            ->where('is_active', true)
            ->where('success_rate', '>', 70)
            ->where('response_time', '<', 2.0)
            ->orderBy('response_time')
            ->get();
            
        if ($fastProxies->isNotEmpty()) {
            // Берём один из топ-3 случайно
            $topProxies = $fastProxies->take(3);
            return $topProxies->random();
        }
        
        // Если нет быстрых, берём любой рабочий
        return self::where('is_active', true)
            ->where('fail_count', '<', 3)
            ->where('success_rate', '>', 50)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Отметить неудачу
     */
    public function markFailed(): void
    {
        $this->increment('fail_count');
        
        if ($this->fail_count >= 3) {
            $this->is_active = false;
        }
        
        $this->save();
    }

    /**
     * Отметить успех
     */
    public function markSuccess(): void
    {
        $this->fail_count = 0;
        $this->is_active = true;
        $this->last_used_at = now();
        $this->save();
    }
}