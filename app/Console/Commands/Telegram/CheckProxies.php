<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\ProxyManager;
use App\Models\TelegramProxy;
use Illuminate\Console\Command;

class CheckProxies extends Command
{
    protected $signature = 'telegram:check-proxies {--all : Проверить все прокси}';
    protected $description = 'Проверка скорости и работоспособности прокси';

    protected ProxyManager $proxyManager;

    public function __construct(ProxyManager $proxyManager)
    {
        parent::__construct();
        $this->proxyManager = $proxyManager;
    }

    public function handle()
    {
        $this->info('🔍 Начинаем проверку прокси...');
        $startTime = microtime(true);

        if ($this->option('all')) {
            $this->checkAllProxies();
        } else {
            $this->info('📊 Используйте --all для проверки всех прокси');
            $this->showProxyStats();
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("⏱️  Время выполнения: {$elapsed} сек");

        return 0;
    }

    protected function showProxyStats()
    {
        $proxies = TelegramProxy::all();
        
        if ($proxies->isEmpty()) {
            $this->warn('📭 Нет прокси в базе. Сначала запустите telegram:collect-proxies');
            return;
        }

        $this->info("📊 Статистика прокси (всего: {$proxies->count()})");
        
        $headers = ['ID', 'Сервер', 'Тип', 'Скорость', 'Успешность', 'Рейтинг'];
        $rows = [];

        foreach ($proxies as $proxy) {
            $rows[] = [
                $proxy->id,
                $proxy->server . ':' . $proxy->port,
                $proxy->type,
                $proxy->response_time ? round($proxy->response_time, 2) . ' сек' : '-',
                $proxy->success_rate ? $proxy->success_rate . '%' : '-',
                $proxy->last_speed_rating ?? '-'
            ];
        }

        $this->table($headers, $rows);
    }

    protected function checkAllProxies()
    {
        $proxies = TelegramProxy::all();
        $total = $proxies->count();
        
        $this->info("📡 Проверка {$total} прокси...");
        $this->newLine();

        $working = 0;
        $fast = 0;
        $results = [];

        foreach ($proxies as $index => $proxy) {
            $this->line("────────────────────────────────────");
            $this->line("🔍 Прокси #" . ($index + 1) . " из {$total}");
            $this->line("   📍 Сервер: {$proxy->server}:{$proxy->port}");
            $this->line("   📋 Тип: {$proxy->type}");
            
            $this->line("   ⏳ Проверка...");
            
            $result = $this->proxyManager->checkProxy($proxy);
            $results[] = $result;
            
            if ($result['success_rate'] > 0) {
                $working++;
                $this->line("   ✅ УСПЕХ! Время ответа: {$result['avg_time']} сек");
                $this->line("   📊 Рейтинг: {$result['speed_rating']}");
                
                if ($result['avg_time'] < 2.0) {
                    $fast++;
                }
            } else {
                $this->line("   ❌ НЕ РАБОТАЕТ");
                if (isset($result['details'][0]['error'])) {
                    $this->line("      Ошибка: {$result['details'][0]['error']}");
                }
            }
            
            // Пауза между проверками
            if ($index < $total - 1) {
                $this->line("   ⏱️  Пауза 1 секунда...");
                sleep(1);
            }
        }

        $this->newLine();
        $this->info("────────────────────────────────────");
        $this->info("📊 ИТОГИ ПРОВЕРКИ:");
        $this->info("   ✅ Рабочих прокси: {$working} из {$total}");
        $this->info("   ⚡ Быстрых (< 2 сек): {$fast}");
        $this->info("   ❌ Нерабочих: " . ($total - $working));

        // Показываем топ-5 самых быстрых
        $fastest = array_filter($results, fn($r) => $r['success_rate'] > 0);
        usort($fastest, fn($a, $b) => $a['avg_time'] <=> $b['avg_time']);
        $fastest = array_slice($fastest, 0, 5);

        if (!empty($fastest)) {
            $this->newLine();
            $this->info("🚀 ТОП-5 САМЫХ БЫСТРЫХ ПРОКСИ:");
            foreach ($fastest as $i => $r) {
                $this->line("   " . ($i+1) . ". {$r['proxy']->server}:{$r['proxy']->port} - {$r['avg_time']} сек ({$r['speed_rating']})");
            }
        }
    }
}