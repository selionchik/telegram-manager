<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            if (!Schema::hasColumn('telegram_chats', 'last_message')) {
                $table->text('last_message')->nullable()->after('last_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->dropColumn('last_message');
        });
    }
};