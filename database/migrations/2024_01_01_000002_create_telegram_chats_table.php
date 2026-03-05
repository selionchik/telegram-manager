<?php
// database/migrations/2024_01_01_000002_create_telegram_chats_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->bigInteger('id')->primary(); // telegram chat id
            $table->foreignId('account_id')->constrained('telegram_accounts')->onDelete('cascade');
            $table->enum('type', ['private', 'group', 'channel', 'supergroup']);
            $table->bigInteger('access_hash')->nullable();
            $table->string('title');
            $table->string('username')->nullable();
            $table->text('about')->nullable();
            $table->bigInteger('last_message_id')->nullable();
            $table->bigInteger('last_scanned_message_id')->nullable();
            $table->integer('unread_count')->default(0);
            $table->integer('participants_count')->default(0);
            $table->json('photo')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_message_date')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['account_id', 'type']);
            $table->index('title');
            $table->index('last_message_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};