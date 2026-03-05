<?php

namespace App\Services\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger;
use danog\MadelineProto\Settings\Serialization;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Stream\MTProtoTransport\ObfuscatedStream;
use danog\MadelineProto\Stream\Proxy\SocksProxy;
use danog\MadelineProto\Logger as MadelineLogger;
use App\Models\TelegramAccount;
use App\Models\TelegramProxy;
use App\Services\Telegram\ProxyManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use danog\MadelineProto\Settings\Ipc; 

class MultiAccountService
{
    private array $instances = [];
    private array $config;
    private ProxyManager $proxyManager;

    public function __construct()
    {
        $this->config = config('telegram.accounts', []);
        $this->proxyManager = app(ProxyManager::class);
    }

    /**
     * Получить экземпляр MadelineProto для аккаунта
     */
    public function getAccount(string $accountName): ?API
    {
        // Проверяем кэш
        if (isset($this->instances[$accountName])) {
            return $this->instances[$accountName];
        }

        // Получаем аккаунт из БД
        $account = TelegramAccount::where('name', $accountName)->first();
        
        if (!$account) {
            Log::error("Аккаунт {$accountName} не найден в БД");
            return null;
        }

        if ($account->status !== 'connected') {
            Log::error("Аккаунт {$accountName} не подключён (статус: {$account->status})");
            return null;
        }

        try {
            $sessionPath = storage_path("telegram/{$accountName}.session");
            
            // Проверяем существование файла сессии
            if (!file_exists($sessionPath)) {
                Log::error("Файл сессии не найден: {$sessionPath}");
                $account->update(['status' => 'disconnected']);
                return null;
            }

            Log::info("Загрузка сессии из: {$sessionPath}");

            $settings = $this->buildSettings($accountName);
            
            $madelineProto = new API($sessionPath, $settings);
            
            // Даем время на инициализацию
            usleep(500000); // 0.5 секунды
            
            // Проверяем авторизацию
            $authStatus = $madelineProto->getAuthorization();
            Log::info("Статус авторизации для {$accountName}: {$authStatus}");
            
            if ($authStatus !== API::LOGGED_IN) {
                Log::error("Аккаунт {$accountName} не авторизован (статус: {$authStatus})");
                $account->update(['status' => 'disconnected']);
                return null;
            }

            // Проверяем, что можем получить информацию о себе
            try {
                $self = $madelineProto->getSelf();
                Log::info("Успешное подключение к аккаунту {$accountName}: " . ($self['first_name'] ?? 'Unknown') . " (ID: {$self['id']})");
            } catch (\Exception $e) {
                Log::warning("Не удалось получить информацию о себе, но авторизация есть");
            }

            $this->instances[$accountName] = $madelineProto;
            
            $account->update([
                'status' => 'connected',
                'last_connected_at' => now(),
            ]);

            return $madelineProto;

        } catch (\Exception $e) {
            Log::error("Ошибка инициализации аккаунта {$accountName}: " . $e->getMessage());
            
            if ($account) {
                $account->update([
                    'status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);
            }
            
            return null;
        }
    }

    /**
     * Получить все активные аккаунты
     */
    public function getAllAccounts(): array
    {
        $accounts = [];
        $activeAccounts = TelegramAccount::where('status', 'connected')->get();

        foreach ($activeAccounts as $account) {
            $instance = $this->getAccount($account->name);
            if ($instance) {
                $accounts[$account->name] = $instance;
            }
        }

        return $accounts;
    }

    /**
     * Получить аккаунт с наименьшей нагрузкой
     */
    public function getAvailableAccount(): ?API
    {
        $account = TelegramAccount::where('status', 'connected')
            ->where('messages_parsed_today', '<', 1000)
            ->orderBy('messages_parsed_today')
            ->first();

        return $account ? $this->getAccount($account->name) : null;
    }

    /**
     * Сборка настроек для MadelineProto
     */
    private function buildSettings(string $accountName): Settings
    {
    $config = $this->config[$accountName] ?? [];
    
    $settings = new Settings();
    
    // Получаем самый быстрый fake_tls прокси
    $proxy = $this->proxyManager->getFastestProxy();
    
    if ($proxy) {
        try {
            $startTime = microtime(true);
            
            $connection = new Connection();
            
            // Все прокси теперь fake_tls, используем ObfuscatedStream
            $connection->addProxy(
                ObfuscatedStream::class,
                [
                    'address' => $proxy->server,
                    'port' => $proxy->port,
                    'secret' => $proxy->secret,
                ]
            );
            Log::info("🔌 Добавлен MTProto прокси", [
                'server' => $proxy->server,
                'port' => $proxy->port,
            ]);
            
            $settings->setConnection($connection);
            
            $connectionTime = round(microtime(true) - $startTime, 2);
            
            Log::info("✅ Используем прокси для аккаунта {$accountName}", [
                'server' => $proxy->server,
                'port' => $proxy->port,
                'type' => $proxy->type,
                'connection_time' => $connectionTime
            ]);
            
            $proxy->markSuccess();
            
        } catch (\Exception $e) {
            Log::error("❌ Ошибка подключения к прокси", [
                'server' => $proxy->server,
                'error' => $e->getMessage()
            ]);
            $proxy->markFailed();
        }
    }
        
        // AppInfo
        $appInfo = (new AppInfo())
            ->setApiId($config['api_id'] ?? env('TG_API_ID_1'))
            ->setApiHash($config['api_hash'] ?? env('TG_API_HASH_1'));
        $settings->setAppInfo($appInfo);
        
        // Logger
$logger = (new Logger())
    ->setType(MadelineLogger::FILE_LOGGER)
    ->setExtra(storage_path("logs/madeline/telegram_{$accountName}.log"))
    ->setLevel(MadelineLogger::ULTRA_VERBOSE)
    ->setMaxSize(50 * 1024 * 1024);
$settings->setLogger($logger);
        
        // Serialization
        $serialization = (new Serialization())
            ->setInterval(300);
        $settings->setSerialization($serialization);

        return $settings;
    }

    /**
     * Авторизация нового аккаунта
     */
    public function authorizeAccount(string $accountName, string $phone): array
    {
        $config = $this->config[$accountName] ?? null;
        
        if (!$config) {
            throw new \Exception("Аккаунт {$accountName} не найден в конфиге");
        }

        $sessionPath = storage_path("telegram/{$accountName}.session");
        
        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0755, true);
        }

        $settings = $this->buildSettings($accountName);

        
        $madelineProto = new API($sessionPath, $settings);

        $sentCode = $madelineProto->phoneLogin($phone);

        $this->instances[$accountName] = $madelineProto;

        $account = TelegramAccount::updateOrCreate(
            ['name' => $accountName],
            [
                'phone' => $phone,
                'status' => 'authorizing',
            ]
        );

        return [
            'account' => $account,
            'sent_code' => $sentCode,
            'type' => $sentCode['_'] ?? 'unknown',
            'phone_code_hash' => $sentCode['phone_code_hash'] ?? null,
        ];
    }

    /**
     * Завершить авторизацию с кодом
     */
    public function completeAuthorization(string $accountName, string $code, ?string $password = null): bool
    {
        $madelineProto = $this->getAccount($accountName);
        
        if (!$madelineProto) {
            throw new \Exception("Сессия для аккаунта {$accountName} не найдена. Начните авторизацию заново.");
        }

        try {
            $authorization = $madelineProto->completePhoneLogin($code);
            
            if ($authorization['_'] === 'account.password') {
                if (!$password) {
                    throw new \Exception('Требуется пароль двухфакторной аутентификации');
                }
                $madelineProto->complete2falogin($password);
            }

            TelegramAccount::where('name', $accountName)->update([
                'status' => 'connected',
                'last_error' => null,
                'last_connected_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            TelegramAccount::where('name', $accountName)->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Сброс дневных лимитов (запускать по крону в полночь)
     */
    public function resetDailyLimits(): void
    {
        TelegramAccount::query()->update(['messages_parsed_today' => 0]);
    }

    /**
     * Проверить и создать необходимые папки
     */
    private function ensureStorageDirectories(): void
    {
        $paths = [
            storage_path('telegram'),
            storage_path('logs'),
        ];
        
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                Log::info("Создана папка: {$path}");
            }
        }
    }
}