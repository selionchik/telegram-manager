<?php
// database/migrations/2024_01_01_000001_create_telegram_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // account1, account2
            $table->string('phone')->nullable();
            $table->string('status')->default('disconnected'); // connected, disconnected, error
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->integer('messages_parsed_today')->default(0);
            $table->json('settings')->nullable(); // доп. настройки для конкретного аккаунта
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_accounts');
    }
};