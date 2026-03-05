<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\ProxyManager;
use App\Models\TelegramProxy;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    protected ProxyManager $proxyManager;

    public function __construct(ProxyManager $proxyManager)
    {
        $this->proxyManager = $proxyManager;
    }

    /**
     * Принудительная смена прокси
     */
    public function change(Request $request)
    {
        // Помечаем текущий прокси как временно неактивный
        $currentProxy = TelegramProxy::where('is_active', true)
            ->where('fail_count', '<', 3)
            ->inRandomOrder()
            ->first();

        if ($currentProxy) {
            $currentProxy->fail_count++;
            $currentProxy->save();
        }

        // Получаем новый прокси
        $newProxy = $this->proxyManager->getFastestProxy();

        if (!$newProxy) {
            // Если нет прокси - пробуем собрать новые
            $collector = app(\App\Services\Telegram\ProxyCollector::class);
            $collector->collect();

            $newProxy = $this->proxyManager->getFastestProxy();
        }

        if ($newProxy) {
            return response()->json([
                'success' => true,
                'proxy' => [
                    'server' => $newProxy->server,
                    'port' => $newProxy->port,
                    'response_time' => round($newProxy->response_time ?? 0, 2) . ' сек',
                    'last_speed_rating' => $newProxy->last_speed_rating ?? 'неизвестно',
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Нет доступных прокси'
        ]);
    }

    /**
     * Список всех прокси
     */
    public function list()
    {
        $proxies = TelegramProxy::orderBy('response_time')
            ->orderByDesc('success_rate')
            ->get();

        return response()->json([
            'data' => $proxies
        ]);
    }

    /**
     * Тест скорости прокси
     */
    public function test(Request $request, $id)
    {
        $proxy = TelegramProxy::find($id);

        if (!$proxy) {
            return response()->json(['error' => 'Прокси не найден'], 404);
        }

        $result = $this->proxyManager->checkProxy($proxy);

        return response()->json($result);
    }

    /**
     * Парсинг прокси из текста
     */
    public function parse(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);

        $text = $request->input('text');
        $proxies = [];

        // Паттерны для поиска прокси
        $patterns = [
            '/tg:\/\/proxy\?server=([^&]+)&port=(\d+)&secret=([^\s<]+)/',
            '/Server:\s*([^\s]+)\s*Port:\s*(\d+)\s*Secret:\s*([^\s]+)/',
            '/(\d+\.\d+\.\d+\.\d+):(\d+):([a-f0-9]{32})/',
            '/([a-zA-Z0-9.-]+):(\d+):([a-f0-9]{32})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $secret = $match[3];
                    $type = $this->detectProxyType($secret);

                    $proxies[] = [
                        'server' => $match[1],
                        'port' => (int) $match[2],
                        'secret' => $secret,
                        'type' => $type,
                    ];
                }
            }
        }

        // Убираем дубликаты
        $proxies = array_unique($proxies, SORT_REGULAR);

        return response()->json([
            'success' => true,
            'proxies' => array_values($proxies)
        ]);
    }

    /**
     * Добавление прокси вручную
     */
    public function add(Request $request)
    {
        $request->validate([
            'proxies' => 'required|array',
            'proxies.*.server' => 'required|string',
            'proxies.*.port' => 'required|integer',
            'proxies.*.secret' => 'required|string',
            'proxies.*.type' => 'nullable|string',
        ]);

        $added = 0;
        $errors = [];

        foreach ($request->proxies as $proxyData) {
            try {
                TelegramProxy::updateOrCreate(
                    [
                        'server' => $proxyData['server'],
                        'port' => $proxyData['port'],
                        'secret' => $proxyData['secret'],
                    ],
                    [
                        'type' => $proxyData['type'] ?? $this->detectProxyType($proxyData['secret']),
                        'source' => 'manual',
                        'last_checked_at' => now(),
                        'is_active' => true,
                        'fail_count' => 0,
                    ]
                );
                $added++;
            } catch (\Exception $e) {
                $errors[] = "Ошибка сохранения {$proxyData['server']}:{$proxyData['port']}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'added' => $added,
            'errors' => $errors
        ]);
    }

    /**
     * Определить тип прокси по секрету
     */
    private function detectProxyType(string $secret): string
    {
        if (str_starts_with($secret, 'ee')) {
            return 'fake_tls';
        }

        if (strlen($secret) === 32 || strlen($secret) === 44) {
            return 'simple';
        }

        return 'unknown';
    }
}
