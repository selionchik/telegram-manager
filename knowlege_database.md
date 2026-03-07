# БАЗА ЗНАНИЙ ПРОЕКТА TELEGRAM MANAGER (ПОЛНЫЙ ЭКСПОРТ)
**Последнее обновление:** 2026-03-07

```markdown
## 🎯 ЦЕЛИ ПРОЕКТА
- Веб-интерфейс для управления Telegram (парсинг каналов, личных сообщений)
- Автоматизация сбора заказов из Telegram-чатов
- Обработка и отображение комментариев к постам
- Скачивание и кэширование медиафайлов
- Стабильная работа в условиях блокировок Telegram в РФ через ротацию прокси

## 🏗 АРХИТЕКТУРА

### Ключевые решения
- Разделение на Gateway (VPS) и Laravel (shared hosting)
- HTTP API — единственный способ коммуникации
- SSE для прогресса загрузки вместо polling
- Хранение файлов: storage/app/public/ + симлинк public/storage
- Ротация прокси на уровне Gateway (авто + ручное)
- ❌ НЕ использовать Docker на Gateway
- ❌ НЕ использовать виртуальное окружение Python

### GATEWAY (VPS)
**Адрес**: 4af690bcc2b8.vps.myjino.ru:49211
**Расположение**: /var/www/html/telegram-gateway/

**Структура:**
/var/www/html/telegram-gateway/
├── gateway.py
├── working_session.session
├── downloads/
├── .env
└── requirements.txt

**Ключевые эндпоинты:**
/api/getMe
/api/getDialogs
/api/getHistory/{chat_id}
/api/getMessage/{chat_id}/{message_id}
/api/getComments/{chat_id}/{post_id}
/api/size/{chat_id}/{message_id}
/download/{chat_id}/{message_id}
/download-sse/{chat_id}/{message_id}

### LARAVEL (STRUCTURE)
app/
├── Console/Commands/Telegram/
│   ├── SyncTelegram.php
│   ├── ClearAllTelegramData.php
│   └── UpdateRepliesCount.php
├── Http/Controllers/Telegram/
│   ├── ChatController.php
│   ├── CommentController.php
│   ├── FileDownloadController.php
│   └── PostController.php
├── Models/
│   ├── TelegramAccount.php
│   ├── TelegramChat.php
│   ├── TelegramMessage.php
│   └── TelegramUserComment.php
└── Services/Telegram/
    └── GatewayService.php

## 🔧 ТЕХНОЛОГИЧЕСКИЙ СТЕК
- Laravel 12, PHP 8.4, MySQL
- Bootstrap 5, jQuery
- Gateway: Python 3.11, Telethon, FastAPI (без Docker/venv)
- Кэш: Laravel Cache (file/database)

## ✅ РЕШЁННЫЕ ПРОБЛЕМЫ
- 2026-03-05: MadelineProto → Gateway + Telethon
- 2026-03-06: Прогресс загрузки → SSE + имитация
- 2026-03-06: Пути к файлам → storage_path() + asset()
- 2026-03-07: Прогресс на 0% → имитация до первого >0
- 2026-03-07: Конфликт с Docker → отказ от Docker

## 🚧 ТЕКУЩИЕ ЗАДАЧИ
- [ ] Ротация прокси в Gateway
  - Автоматическое переключение при ошибках
  - Ручное переключение через интерфейс
  - API для управления (/change, /list, /add)
  - Хранение пула прокси
- [ ] Поддержка нескольких аккаунтов
- [ ] Поиск по сообщениям
- [ ] Автозапуск Gateway через systemd

## 📝 ВАЖНЫЕ ПАТТЕРНЫ
### Пути к файлам
$savePath = storage_path("app/public/telegram/downloads/{$fileName}");
'downloaded_file' => "telegram/downloads/{$fileName}"
asset('storage/' . $message->downloaded_file)

### SSE прогресс
const eventSource = new EventSource(sseUrl);
eventSource.addEventListener('progress', (e) => {
    const data = JSON.parse(e.data);
    progressBar.css('width', data.percent + '%');
});

### Имитация до реальных данных
if (data.percent > 0 && !realDataStarted) {
    realDataStarted = true;
    clearInterval(simulationInterval);
}

### Запуск Gateway
cd /var/www/html/telegram-gateway
python3 gateway.py

## ❌ ЧЕГО НЕ ДЕЛАТЬ
- Не использовать Cache для прогресса
- Не хранить файлы в public напрямую
- Не смешивать логику исключения чатов и комментариев
- Не использовать Docker на Gateway
- Не создавать виртуальное окружение Python

## 📚 ССЫЛКИ
- Репозиторий Laravel: https://github.com/selionchik/telegram-manager
- Gateway (VPS): 4af690bcc2b8.vps.myjino.ru:49211
- Документация Telethon: https://docs.telethon.dev
- FastAPI: https://fastapi.tiangolo.com