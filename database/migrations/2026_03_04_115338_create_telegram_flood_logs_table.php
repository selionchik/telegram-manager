<?php
// database/migrations/xxxx_create_telegram_flood_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_flood_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->foreignId('account_id')->constrained('telegram_accounts');
            $table->integer('wait_seconds');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_flood_logs');
    }
};