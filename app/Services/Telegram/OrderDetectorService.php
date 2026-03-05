<?php

namespace App\Services\Telegram;

use App\Models\TelegramMessage;
use App\Models\TelegramChat;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OrderDetectorService
{
    /**
     * Паттерны для поиска заказов
     */
    protected array $patterns = [
        'article' => [
            '/#(\d+)/',                    // #3456
            '/арт[.\s]*?(\d+)/i',           // арт3456, арт.3456
            '/артикул[.\s]*?(\d+)/ui',      // артикул 3456
            '/art[.\s]*?(\d+)/i',           // art3456
            '/№\s*(\d+)/u',                  // №3456
            '/(?<!\d)(\d{4,})(?!\d)/',       // просто 4+ цифры (но не в составе других чисел)
        ],
        'quantity' => [
            '/(\d+[.,]?\d*)[.\s]*(метр|м|м\.)/ui',
            '/(\d+[.,]?\d*)[.\s]*(погон)/ui',
            '/нужно\s+(\d+[.,]?\d*)/ui',
            '/надо\s+(\d+[.,]?\d*)/ui',
            '/(\d+[.,]?\d*)\s*м\b/ui',
            '/^(\d+[.,]?\d*)\s*$/u',        // просто число в начале строки
        ],
        'color' => [
            '/(красн|алый|бордов)/ui',
            '/(син|голуб|лазур)/ui',
            '/(зелен|изумруд|салат)/ui',
            '/(желт|золот|солнеч)/ui',
            '/(бел|снежн|молоч)/ui',
            '/(черн|уголь|графит)/ui',
            '/(розов|фукси|пурпур)/ui',
            '/(фиолет|сирен|лилов)/ui',
            '/(коричн|бежев|шоколад)/ui',
            '/(сер|сереб|пепел)/ui',
        ],
        'fabric_type' => [
            '/(атлас|сатин)/ui',
            '/(кружев|гипюр)/ui',
            '/(шелк|шифон)/ui',
            '/(хлопок|бязь|поплин)/ui',
            '/(трикотаж|футер|кулир)/ui',
            '/(бархат|велюр)/ui',
            '/(вискоза|модал)/ui',
            '/(лайкра|спандекс|эластан)/ui',
        ],
    ];

    /**
     * Маппинг цветов к стандартным названиям
     */
    protected array $colorMap = [
        'красн' => 'красный',
        'алый' => 'красный',
        'бордов' => 'бордовый',
        'син' => 'синий',
        'голуб' => 'голубой',
        'лазур' => 'голубой',
        'зелен' => 'зеленый',
        'изумруд' => 'изумрудный',
        'салат' => 'салатовый',
        'желт' => 'желтый',
        'золот' => 'золотой',
        'бел' => 'белый',
        'снежн' => 'белый',
        'черн' => 'черный',
        'уголь' => 'черный',
        'розов' => 'розовый',
        'фукси' => 'фуксия',
        'пурпур' => 'пурпурный',
        'фиолет' => 'фиолетовый',
        'сирен' => 'сиреневый',
        'лилов' => 'лиловый',
        'коричн' => 'коричневый',
        'бежев' => 'бежевый',
        'сер' => 'серый',
        'сереб' => 'серебристый',
        'пепел' => 'пепельный',
    ];

    /**
     * Найти заказы в непрочитанных сообщениях
     */
    public function findNewOrders(): array
    {
        $orders = [];
        
        // Берём сообщения, которые ещё не обработаны
        $messages = TelegramMessage::with('chat')
            ->where('processed', false)
            ->where('date', '>=', now()->subDays(7))
            ->orderBy('date')
            ->limit(100)
            ->get();

        foreach ($messages as $message) {
            $orderData = $this->analyzeMessage($message);
            
            if ($orderData) {
                $order = $this->createOrder($message, $orderData);
                $orders[] = $order;
                
                // Отмечаем сообщение как обработанное
                $message->markAsProcessed();
            }
        }

        return $orders;
    }

    /**
     * Анализ конкретного сообщения
     */
    public function analyzeMessage(TelegramMessage $message): ?array
    {
        $text = trim($message->text);
        
        if (empty($text) || strlen($text) < 3) {
            return null;
        }

        $detected = [
            'article' => null,
            'quantity' => null,
            'unit' => 'метр',
            'color' => null,
            'fabric_type' => null,
            'confidence' => 0,
            'matches' => [],
        ];

        // Поиск артикулов (приоритет: с решёткой > арт > просто цифры)
        foreach ($this->patterns['article'] as $index => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $article) {
                    // Проверяем, что это число
                    if (is_numeric($article)) {
                        $detected['article'] = (int) $article;
                        $detected['matches'][] = ['type' => 'article', 'value' => "#{$article}"];
                        
                        // Чем раньше паттерн в списке, тем выше уверенность
                        $detected['confidence'] += (5 - $index) * 0.1;
                        break 2; // выходим из обоих циклов
                    }
                }
            }
        }

        // Поиск количества
        foreach ($this->patterns['quantity'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $quantity = str_replace(',', '.', $matches[1]);
                if (is_numeric($quantity)) {
                    $detected['quantity'] = (float) $quantity;
                    $detected['matches'][] = ['type' => 'quantity', 'value' => $matches[0]];
                    $detected['confidence'] += 0.3;
                    
                    // Определяем единицу измерения
                    if (isset($matches[2])) {
                        $detected['unit'] = 'метр';
                    }
                }
                break;
            }
        }

        // Поиск цвета
        foreach ($this->patterns['color'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $colorKey = $matches[1];
                $detected['color'] = $this->normalizeColor($colorKey);
                $detected['matches'][] = ['type' => 'color', 'value' => $matches[0]];
                $detected['confidence'] += 0.2;
                break;
            }
        }

        // Поиск типа ткани
        foreach ($this->patterns['fabric_type'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $detected['fabric_type'] = $matches[1];
                $detected['matches'][] = ['type' => 'fabric', 'value' => $matches[0]];
                $detected['confidence'] += 0.1;
                break;
            }
        }

        // Если уверенность выше порога, возвращаем результат
        return $detected['confidence'] >= 0.3 ? $detected : null;
    }

    /**
     * Нормализация цвета
     */
    protected function normalizeColor(string $colorKey): string
    {
        foreach ($this->colorMap as $key => $value) {
            if (str_contains(mb_strtolower($colorKey), $key)) {
                return $value;
            }
        }
        return $colorKey;
    }

    /**
     * Создание заказа в БД
     */
    protected function createOrder(TelegramMessage $message, array $detected): Order
    {
        return Order::create([
            'message_id' => $message->message_id,
            'chat_id' => $message->chat_id,
            'user_id' => $message->from_id ?? 0,
            'user_name' => $message->from_name,
            'article' => $detected['article'],
            'color' => $detected['color'],
            'quantity' => $detected['quantity'],
            'unit' => $detected['unit'] ?? 'метр',
            'original_text' => $message->text,
            'detected_items' => $detected['matches'],
            'confidence' => $detected['confidence'],
            'order_date' => $message->date,
            'status' => 'new',
        ]);
    }

    /**
     * Поиск заказов по артикулу
     */
    public function findByArticle(int $article): array
    {
        return Order::where('article', $article)
            ->with('chat')
            ->orderBy('order_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Поиск заказов за период
     */
    public function findByPeriod(string $from, string $to): array
    {
        return Order::whereBetween('order_date', [$from, $to])
            ->orderBy('order_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Получить статистику по заказам
     */
    public function getStatistics(): array
    {
        return [
            'today' => Order::whereDate('order_date', today())->count(),
            'week' => Order::where('order_date', '>=', now()->subDays(7))->count(),
            'month' => Order::where('order_date', '>=', now()->subMonth())->count(),
            'total' => Order::count(),
            'by_status' => [
                'new' => Order::where('status', 'new')->count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'processing' => Order::where('status', 'processing')->count(),
                'done' => Order::where('status', 'done')->count(),
            ],
        ];
    }

    /**
     * Подтвердить заказ
     */
    public function confirmOrder(int $orderId): bool
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return false;
        }

        return $order->update(['status' => 'confirmed']);
    }

    /**
     * Отметить заказ как выполненный
     */
    public function completeOrder(int $orderId): bool
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return false;
        }

        return $order->update(['status' => 'done']);
    }
}