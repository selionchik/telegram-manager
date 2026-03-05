<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\ChatExclusionService;
use Illuminate\Console\Command;

class TelegramInclude extends Command
{
    protected $signature = 'telegram:include {chat_id}';

    protected $description = 'Вернуть чат в парсинг';

    protected ChatExclusionService $exclusionService;

    public function __construct(ChatExclusionService $exclusionService)
    {
        parent::__construct();
        $this->exclusionService = $exclusionService;
    }

    public function handle()
    {
        $chatId = $this->argument('chat_id');

        if ($this->exclusionService->includeChat($chatId)) {
            $this->info("Чат {$chatId} возвращён в парсинг");
            return 0;
        }

        $this->error("Чат {$chatId} не найден");
        return 1;
    }
}