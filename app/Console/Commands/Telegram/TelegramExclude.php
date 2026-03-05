<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\ChatExclusionService;
use Illuminate\Console\Command;

class TelegramExclude extends Command
{
    protected $signature = 'telegram:exclude 
                            {chat_id : ID чата}
                            {reason? : Причина исключения}';

    protected $description = 'Исключить чат из парсинга';

    protected ChatExclusionService $exclusionService;

    public function __construct(ChatExclusionService $exclusionService)
    {
        parent::__construct();
        $this->exclusionService = $exclusionService;
    }

    public function handle()
    {
        $chatId = $this->argument('chat_id');
        $reason = $this->argument('reason');

        if ($this->exclusionService->excludeChat($chatId, $reason)) {
            $this->info("Чат {$chatId} исключён из парсинга");
            return 0;
        }

        $this->error("Чат {$chatId} не найден");
        return 1;
    }
}