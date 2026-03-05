<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('downloaded_file');
            $table->boolean('file_url_clicked')->default(false)->after('thumbnail_path');
            $table->timestamp('file_downloaded_at')->nullable()->after('file_url_clicked');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_path', 'file_url_clicked', 'file_downloaded_at']);
        });
    }
};