<?php

namespace App\Services\Telegram;

use App\Models\TelegramProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProxyManager
{
    /**
     * Тестовый URL для проверки скорости
     */
    protected string $testUrl = 'http://www.google.com/generate_204';
    
    /**
     * Максимально допустимое время ответа (секунды)
     */
    protected float $maxAllowedTime = 3.0;
    
    /**
     * Количество попыток проверки
     */
    protected int $testAttempts = 2;

    /**
     * Проверить прокси (только HTTP прокси, MTProto не проверяем)
     */
/**
 * Быстрая проверка прокси (только TCP ping)
 */
/**
 * Проверить прокси
 */
public function checkProxy(TelegramProxy $proxy): array
{
    // Для MTProto прокси (fake_tls) используем специальную проверку
    if ($proxy->type === 'fake_tls') {
        return $this->checkMTProtoProxy($proxy);
    }
    
    // Для обычных прокси - TCP проверка
    return $this->checkTCPProxy($proxy);
}

/**
 * Проверить MTProto прокси
 */
private function checkMTProtoProxy(TelegramProxy $proxy): array
{
    $results = [];
    
    for ($i = 0; $i < $this->testAttempts; $i++) {
        $startTime = microtime(true);
        
        try {
            // Пытаемся установить соединение через прокси
            $socket = @fsockopen(
                $proxy->server,
                $proxy->port,
                $errno,
                $errstr,
                5 // таймаут 5 секунд
            );
            
            $endTime = microtime(true);
            $elapsed = round($endTime - $startTime, 2);
            
            if ($socket) {
                fclose($socket);
                
                // Дополнительно проверяем, что прокси отвечает как MTProto
                // Отправляем приветствие MTProto
                $socket = @fsockopen($proxy->server, $proxy->port, $errno, $errstr, 3);
                if ($socket) {
                    // Отправляем случайные данные (имитация handshake)
                    fwrite($socket, "\xef" . random_bytes(64));
                    $response = fread($socket, 1);
                    fclose($socket);
                    
                    // Если получили ответ - прокси живой
                    $success = $response !== false;
                } else {
                    $success = false;
                }
                
                $results[] = [
                    'success' => $success,
                    'time' => $elapsed,
                    'error' => $success ? null : 'Не отвечает как MTProto'
                ];
            } else {
                $results[] = [
                    'success' => false,
                    'error' => $errstr,
                    'time' => $elapsed
                ];
            }
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $elapsed = round($endTime - $startTime, 2);
            
            $results[] = [
                'success' => false,
                'error' => $e->getMessage(),
                'time' => $elapsed
            ];
        }
        
        usleep(100000);
    }
    
    return $this->analyzeResults($proxy, $results);
}

/**
 * Проверить обычный прокси через TCP
 */
private function checkTCPProxy(TelegramProxy $proxy): array
{
    $results = [];
    
    for ($i = 0; $i < $this->testAttempts; $i++) {
        $startTime = microtime(true);
        
        try {
            $socket = @fsockopen(
                $proxy->server,
                $proxy->port,
                $errno,
                $errstr,
                3
            );
            
            $endTime = microtime(true);
            $elapsed = round($endTime - $startTime, 2);
            
            if ($socket) {
                fclose($socket);
                $results[] = [
                    'success' => true,
                    'time' => $elapsed,
                    'error' => null
                ];
            } else {
                $results[] = [
                    'success' => false,
                    'error' => $errstr,
                    'time' => $elapsed
                ];
            }
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $elapsed = round($endTime - $startTime, 2);
            
            $results[] = [
                'success' => false,
                'error' => $e->getMessage(),
                'time' => $elapsed
            ];
        }
        
        usleep(100000);
    }
    
    return $this->analyzeResults($proxy, $results);
}

    /**
     * Анализ результатов проверки
     */
    protected function analyzeResults(TelegramProxy $proxy, array $results): array
    {
        $successful = array_filter($results, fn($r) => $r['success']);
        $successCount = count($successful);
        
        $avgTime = $successCount > 0 
            ? array_sum(array_column($successful, 'time')) / $successCount
            : 999;
        
        $isHealthy = $successCount > 0 && $avgTime <= $this->maxAllowedTime;
        $speedRating = $this->getSpeedRating($avgTime);
        
        // Обновляем прокси в БД
        $proxy->update([
            'last_checked_at' => now(),
            'response_time' => $avgTime,
            'success_rate' => round(($successCount / $this->testAttempts) * 100),
            'is_active' => $successCount > 0, // Хотя бы одна успешная попытка
            'fail_count' => $successCount > 0 ? 0 : $proxy->fail_count + 1,
            'last_speed_rating' => $speedRating,
        ]);
        
        // Кэшируем результат на 5 минут
        Cache::put("proxy_{$proxy->id}_speed", $avgTime, 300);
        
        return [
            'proxy' => $proxy,
            'avg_time' => $avgTime,
            'success_rate' => ($successCount / $this->testAttempts) * 100,
            'speed_rating' => $speedRating,
            'is_healthy' => $isHealthy,
            'details' => $results
        ];
    }

    /**
     * Получить текстовую оценку скорости
     */
    protected function getSpeedRating(float $time): string
    {
        return match(true) {
            $time < 0.5 => '🚀 Молниеносно',
            $time < 1.0 => '⚡ Очень быстро',
            $time < 2.0 => '✅ Нормально',
            $time < 3.0 => '⚠️ Медленно',
            default => '❌ Тормоз'
        };
    }

    /**
     * Получить самый быстрый прокси (только HTTP)
     */
/**
 * Получить самый быстрый прокси (только fake_tls)
 */
public function getFastestProxy(): ?TelegramProxy
{
    // Сначала проверяем кэш
    $cached = Cache::get('fastest_proxy_id');
    if ($cached) {
        $proxy = TelegramProxy::find($cached);
        if ($proxy && $proxy->is_active) {
            return $proxy;
        }
    }
    
    // Только fake_tls прокси
    $proxy = TelegramProxy::where('is_active', true)
        ->where('type', 'fake_tls')
        ->where('success_rate', '>', 0)
        ->orderBy('response_time', 'asc')
        ->first();
        
    if ($proxy) {
        Cache::put('fastest_proxy_id', $proxy->id, 300);
    }
    
    return $proxy;
}

    /**
     * Проверить все прокси (для крона)
     */
    public function checkAllProxies(): array
    {
        $proxies = TelegramProxy::where('is_active', true)
            ->orWhere('fail_count', '<', 5)
            ->get();
            
        $results = [];
        
        foreach ($proxies as $proxy) {
            Log::info("Проверка прокси {$proxy->id} ({$proxy->server}:{$proxy->port})");
            $result = $this->checkProxy($proxy);
            $results[] = $result;
            
            // Пауза между проверками
            sleep(1);
        }
        
        return $results;
    }
    
}