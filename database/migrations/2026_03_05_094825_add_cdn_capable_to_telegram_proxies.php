<?php
// database/migrations/xxxx_add_cdn_capable_to_telegram_proxies.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_proxies', function (Blueprint $table) {
            $table->boolean('cdn_capable')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_proxies', function (Blueprint $table) {
            $table->dropColumn('cdn_capable');
        });
    }
};