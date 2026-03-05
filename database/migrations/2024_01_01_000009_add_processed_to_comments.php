<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_user_comments', function (Blueprint $table) {
            $table->boolean('processed')->default(false)->after('text');
            $table->timestamp('processed_at')->nullable()->after('processed');
            $table->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_user_comments', function (Blueprint $table) {
            $table->dropColumn(['processed', 'processed_at', 'processed_by']);
        });
    }
};