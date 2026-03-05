<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('is_pinned');
            $table->timestamp('excluded_at')->nullable()->after('is_excluded');
            $table->text('excluded_reason')->nullable()->after('excluded_at');
            $table->integer('exclude_count')->default(0)->after('excluded_reason');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->dropColumn(['is_excluded', 'excluded_at', 'excluded_reason', 'exclude_count']);
        });
    }
};