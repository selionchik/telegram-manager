<?php

namespace App\Services\Telegram;

use App\Models\TelegramProxy;
use Illuminate\Support\Facades\Log;

class ProxyCollector
{
    protected MultiAccountService $multiAccount;
    
    /**
     * Каналы для сбора прокси
     */
    protected array $channels = [
        '@ProxyMTProto',
    ];

    public function __construct(MultiAccountService $multiAccount)
    {
        $this->multiAccount = $multiAccount;
    }

    /**
     * Собрать прокси из всех каналов
     */
    public function collect(): array
    {
        Log::info('🔍 Начинаем сбор прокси...');
        
        $allProxies = [];
        
        // Берём первый доступный аккаунт
        $accounts = $this->multiAccount->getAllAccounts();
        
        if (empty($accounts)) {
            Log::error('❌ Нет доступных аккаунтов для сбора прокси');
            return [];
        }

        $madeline = reset($accounts);
        
        foreach ($this->channels as $channel) {
            $proxies = $this->collectFromChannel($madeline, $channel);
            $allProxies = array_merge($allProxies, $proxies);
            
            // Небольшая задержка между каналами
            if ($channel !== end($this->channels)) {
                sleep(2);
            }
        }
        
        // Сохраняем в БД
        $saved = 0;
        foreach ($allProxies as $proxy) {
            if ($this->saveProxy($proxy)) {
                $saved++;
            }
        }
        
        Log::info("✅ Сбор прокси завершен", [
            'найдено' => count($allProxies),
            'сохранено' => $saved
        ]);
        
        return $allProxies;
    }

    /**
     * Собрать прокси из конкретного канала
     */
    protected function collectFromChannel($madeline, string $channel): array
    {
        Log::info("📡 Проверяем канал {$channel}...");
        
        try {
            // Получаем последние сообщения из канала
            $history = $madeline->messages->getHistory(
                peer: $channel,
                limit: 30
            );

            $messages = $history['messages'] ?? [];
            Log::info("   → Получено сообщений: " . count($messages));

            $proxies = [];
            
            foreach ($messages as $message) {
                $text = $message['message'] ?? '';
                if (empty($text)) continue;
                
                $found = $this->extractProxiesFromText($text, $channel);
                $proxies = array_merge($proxies, $found);
            }
            
            Log::info("   🔍 Найдено прокси: " . count($proxies));
            
            return $proxies;
            
        } catch (\Exception $e) {
            Log::error("❌ Ошибка канала {$channel}: " . $e->getMessage());
            return [];
        }
    }

/**
 * Извлечь прокси из текста
 */
protected function extractProxiesFromText(string $text, string $source): array
{
    $proxies = [];
    
    // ФОРМАТ 1: tg://proxy?server=xxx&port=yyy&secret=zzz
    preg_match_all('/tg:\/\/proxy\?server=([^&]+)&port=(\d+)&secret=([^\s<]+)/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $secret = $match[3];
        $cdn_capable = str_starts_with($secret, 'ee');
        
        $proxies[] = [
            'server' => $match[1],
            'port' => (int) $match[2],
            'secret' => $secret,
            'source' => $source,
            'type' => $cdn_capable ? 'fake_tls' : 'simple',
            'cdn_capable' => $cdn_capable,
        ];
    }
    
    // ФОРМАТ 2: https://t.me/proxy?server=xxx&port=yyy&secret=zzz
    preg_match_all('/https:\/\/t\.me\/proxy\?server=([^&]+)&port=(\d+)&secret=([^\s<]+)/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $secret = $match[3];
        $cdn_capable = str_starts_with($secret, 'ee');
        
        $proxies[] = [
            'server' => $match[1],
            'port' => (int) $match[2],
            'secret' => $secret,
            'source' => $source,
            'type' => $cdn_capable ? 'fake_tls' : 'simple',
            'cdn_capable' => $cdn_capable,
        ];
    }
    
    // ФОРМАТ 3: server:port:secret
    preg_match_all('/(\d+\.\d+\.\d+\.\d+):(\d+):([a-f0-9]{32,})/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $secret = $match[3];
        $cdn_capable = str_starts_with($secret, 'ee');
        
        $proxies[] = [
            'server' => $match[1],
            'port' => (int) $match[2],
            'secret' => $secret,
            'source' => $source,
            'type' => $cdn_capable ? 'fake_tls' : 'simple',
            'cdn_capable' => $cdn_capable,
        ];
    }
    
    // ФОРМАТ 4: Server: xxx Port: yyy Secret: zzz (как в @ProxyMTProto)
    if (preg_match_all('/Server:\s*([^\s]+)\s*Port:\s*(\d+)\s*Secret:\s*([^\s]+)/', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $secret = $match[3];
            $cdn_capable = str_starts_with($secret, 'ee');
            
            $proxies[] = [
                'server' => $match[1],
                'port' => (int) $match[2],
                'secret' => $secret,
                'source' => $source,
                'type' => $cdn_capable ? 'fake_tls' : 'simple',
                'cdn_capable' => $cdn_capable,
            ];
        }
    }
    
    // ФОРМАТ 5: Многострочный формат
    if (preg_match_all('/Server:\s*([^\s]+).*?Port:\s*(\d+).*?Secret:\s*([^\s]+)/s', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $secret = $match[3];
            $cdn_capable = str_starts_with($secret, 'ee');
            
            $proxies[] = [
                'server' => $match[1],
                'port' => (int) $match[2],
                'secret' => $secret,
                'source' => $source,
                'type' => $cdn_capable ? 'fake_tls' : 'simple',
                'cdn_capable' => $cdn_capable,
            ];
        }
    }
    
    // Убираем дубликаты по комбинации server:port:secret
    $unique = [];
    foreach ($proxies as $proxy) {
        $key = $proxy['server'] . ':' . $proxy['port'] . ':' . $proxy['secret'];
        $unique[$key] = $proxy;
    }
    
    return array_values($unique);
}
    /**
     * Определить тип прокси по секрету
     */
    protected function detectProxyType(string $secret): string
    {
        // Fake-TLS прокси (начинаются с ee)
        if (str_starts_with($secret, 'ee')) {
            return 'fake_tls';
        }
        
        // Обычные прокси
        if (strlen($secret) === 32 || strlen($secret) === 44) {
            return 'simple';
        }
        
        return 'unknown';
    }

    /**
     * Сохранить прокси в БД
     */
    protected function saveProxy(array $proxyData): bool
    {
        try {
            // Не сохраняем прокси с "Unknown" в сервере
            if ($proxyData['server'] === 'Unknown' || $proxyData['server'] === 'unknown') {
                return false;
            }
            
            TelegramProxy::updateOrCreate(
                [
                    'server' => $proxyData['server'],
                    'port' => $proxyData['port'],
                    'secret' => $proxyData['secret'],
                ],
                [
                    'type' => $proxyData['type'],
                    'source' => $proxyData['source'],
                     'cdn_capable' => $proxyData['cdn_capable'] ?? false,
                    'last_checked_at' => now(),
                    'is_active' => true,
                    'fail_count' => 0,
                ]
            );
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Ошибка сохранения прокси: " . $e->getMessage());
            return false;
        }
    }

    
}