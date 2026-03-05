<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\OrderDetectorService;
use Illuminate\Console\Command;

class TelegramCheckOrders extends Command
{
    protected $signature = 'telegram:orders 
                            {--limit=100 : Максимум сообщений для проверки}';

    protected $description = 'Поиск заказов в новых сообщениях';

    protected OrderDetectorService $orderDetector;

    public function __construct(OrderDetectorService $orderDetector)
    {
        parent::__construct();
        $this->orderDetector = $orderDetector;
    }

    public function handle()
    {
        $this->info('Поиск заказов...');

        $orders = $this->orderDetector->findNewOrders();

        if (empty($orders)) {
            $this->info('Новых заказов не найдено');
            return 0;
        }

        $this->info("Найдено заказов: " . count($orders));

        $headers = ['ID', 'Артикул', 'Кол-во', 'Цвет', 'Текст', 'Уверенность'];
        $rows = [];

        foreach ($orders as $order) {
            $rows[] = [
                $order->id,
                $order->article ?? '-',
                $order->quantity ? $order->quantity . ' ' . $order->unit : '-',
                $order->color ?? '-',
                mb_substr($order->original_text, 0, 30) . '...',
                round($order->confidence * 100) . '%',
            ];
        }

        $this->table($headers, $rows);
        $this->info('Заказы сохранены в БД');

        return 0;
    }
}