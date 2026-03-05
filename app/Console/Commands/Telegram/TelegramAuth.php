<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\MultiAccountService;
use Illuminate\Console\Command;
use danog\MadelineProto\API;

class TelegramAuth extends Command
{
    protected $signature = 'telegram:auth 
                            {account : Имя аккаунта (account1, account2)}
                            {--code= : Код подтверждения из Telegram}
                            {--password= : Пароль 2FA (если включена)}
                            {--qr : Использовать QR-код для авторизации}';

    protected $description = 'Авторизация Telegram аккаунта';

    protected MultiAccountService $multiAccount;

    public function __construct(MultiAccountService $multiAccount)
    {
        parent::__construct();
        $this->multiAccount = $multiAccount;
    }

    public function handle()
    {
        $accountName = $this->argument('account');
        $config = config("telegram.accounts.{$accountName}");

        if (!$config) {
            $this->error("Аккаунт {$accountName} не найден в config/telegram.php");
            return 1;
        }

        // Если выбран QR-код
        if ($this->option('qr')) {
            return $this->qrLogin($accountName, $config);
        }

        // Обычная авторизация через код
        return $this->codeLogin($accountName, $config);
    }

    /**
     * Авторизация через QR-код
     */
    protected function qrLogin(string $accountName, array $config): int
    {
        $this->info("Начинаем QR-авторизацию для аккаунта {$accountName}");

        try {
            $sessionPath = storage_path("telegram/{$accountName}.session");
            
            // Создаём папку если нет
            if (!is_dir(dirname($sessionPath))) {
                mkdir(dirname($sessionPath), 0755, true);
            }

            $settings = $this->multiAccount->buildSettings($accountName);
            $madelineProto = new API($sessionPath, $settings);

            // Проверяем, может уже авторизован
            if ($madelineProto->getAuthorization() === API::LOGGED_IN) {
                $this->info('✅ Аккаунт уже авторизован!');
                return 0;
            }

            $this->info('🔄 Генерируем QR-код...');

            do {
                $qr = $madelineProto->qrLogin();
                
                if (!$qr) {
                    $auth = $madelineProto->getAuthorization();
                    if ($auth === API::WAITING_PASSWORD) {
                        $password = $this->secret('Введите пароль двухфакторной аутентификации: ');
                        $madelineProto->complete2faLogin($password);
                    }
                    break;
                }

                // Выводим QR-код как ссылку
                $qrText = $qr->getQRText();
                
                $this->line("\n" . str_repeat('=', 50));
                $this->line('     📱 ОТСКАНИРУЙТЕ QR-КОД');
                $this->line(str_repeat('=', 50));
                $this->line("\nСсылка для сканирования:");
                $this->info($qrText);
                $this->line("\n" . str_repeat('=', 50));
                
                $this->line("\n📋 Инструкция:");
                $this->line("1. Откройте Telegram на телефоне");
                $this->line("2. Перейдите в Настройки → Устройства");
                $this->line("3. Нажмите 'Сканировать QR-код'");
                $this->line("4. Наведите камеру на QR-код выше");
                
                $this->line("\n⏳ Ожидание сканирования...");
                
                // Ждём сканирования (до 60 секунд)
                $newQr = $qr->waitForLoginOrQrCodeExpiration();
                
                if ($newQr) {
                    $this->line("\n🔄 QR-код истёк, генерируем новый...");
                    continue;
                }
                
                $this->line("\n✅ QR-код отсканирован!");
                
                if ($madelineProto->getAuthorization() === API::WAITING_PASSWORD) {
                    $password = $this->secret('Введите пароль двухфакторной аутентификации: ');
                    $madelineProto->complete2faLogin($password);
                }
                
                break;
                
            } while (true);

            // Получаем информацию о пользователе
            $me = $madelineProto->getSelf();
            
            // Обновляем статус в БД
            \App\Models\TelegramAccount::updateOrCreate(
                ['name' => $accountName],
                [
                    'phone' => $config['phone'] ?? null,
                    'status' => 'connected',
                    'last_connected_at' => now(),
                ]
            );

            $this->info("\n✅ Авторизация успешна!");
            $this->line("👤 ID: " . ($me['id'] ?? 'неизвестно'));
            $this->line("👤 Имя: " . ($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
            
            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Ошибка: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Авторизация через код
     */
    protected function codeLogin(string $accountName, array $config): int
    {
        $phone = $config['phone'] ?? $this->ask('Введите номер телефона (в формате +79001234567)');

        try {
            $result = $this->multiAccount->authorizeAccount($accountName, $phone);
            
            $this->info('Код подтверждения отправлен!');
            
            $type = $result['sent_code']['type'] ?? [];
            if (is_array($type)) {
                $typeName = $type['_'] ?? 'unknown';
                $length = $type['length'] ?? '?';
                $this->line("Код отправлен в приложение Telegram (длина: {$length})");
            } else {
                $this->line("Тип кода: {$type}");
            }

            if ($code = $this->option('code')) {
                $this->completeCodeAuth($accountName, $code);
                return 0;
            }

            $this->line('Запустите команду ещё раз с --code=ВАШ_КОД');
            
        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function completeCodeAuth(string $accountName, string $code): void
    {
        try {
            $this->multiAccount->completeAuthorization(
                $accountName, 
                $code, 
                $this->option('password')
            );
            
            $this->info('Аккаунт успешно авторизован!');
            
        } catch (\Exception $e) {
            $this->error('Ошибка завершения авторизации: ' . $e->getMessage());
        }
    }
}