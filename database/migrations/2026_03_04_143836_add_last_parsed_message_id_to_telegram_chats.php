<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->bigInteger('last_parsed_message_id')->nullable()->after('last_scanned_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->dropColumn('last_parsed_message_id');
        });
    }
};