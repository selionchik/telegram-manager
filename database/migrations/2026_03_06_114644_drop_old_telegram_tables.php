<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Удаляем таблицы, которые больше не нужны
        Schema::dropIfExists('telegram_proxies');
        Schema::dropIfExists('telegram_flood_logs');
    }

    public function down(): void
    {
        // Таблицы не восстанавливаем - они больше не нужны
        // Если очень хочется, можно создать заново, но структура не понадобится
    }
};