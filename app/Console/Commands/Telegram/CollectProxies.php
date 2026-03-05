<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\ProxyCollector;
use Illuminate\Console\Command;

class CollectProxies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:collect-proxies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сбор свежих MTProto прокси из Telegram каналов';

    protected ProxyCollector $collector;

    /**
     * Create a new command instance.
     */
    public function __construct(ProxyCollector $collector)
    {
        parent::__construct();
        $this->collector = $collector;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начинаем сбор прокси...');

        try {
            $proxies = $this->collector->collect();

            $this->info("✅ Собрано прокси: " . count($proxies));
            
            if (count($proxies) > 0) {
                $this->table(
                    ['Сервер', 'Порт', 'Тип', 'Источник'],
                    array_map(fn($p) => [
                        $p['server'],
                        $p['port'],
                        $p['type'],
                        $p['source'] ?? 'unknown'
                    ], $proxies)
                );
            } else {
                $this->warn('Прокси не найдены. Попробуйте позже.');
            }

        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
        }

        return 0;
    }
}