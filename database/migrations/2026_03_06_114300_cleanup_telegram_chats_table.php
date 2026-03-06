<?php
// database/migrations/xxxx_cleanup_telegram_chats_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            // Удаляем лишние поля
            $table->dropColumn([
                'access_hash',
                'about',
                'last_scanned_message_id',
                'last_parsed_message_id',
                'is_pinned',
                'last_scanned_at',
            ]);
            
            // Добавляем last_read_message_id, если его нет
            if (!Schema::hasColumn('telegram_chats', 'last_read_message_id')) {
                $table->bigInteger('last_read_message_id')->nullable()->after('unread_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            // Восстанавливаем на случай отката
            $table->bigInteger('access_hash')->nullable();
            $table->text('about')->nullable();
            $table->bigInteger('last_scanned_message_id')->nullable();
            $table->bigInteger('last_parsed_message_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_scanned_at')->nullable();
            
            $table->dropColumn('last_read_message_id');
        });
    }
};