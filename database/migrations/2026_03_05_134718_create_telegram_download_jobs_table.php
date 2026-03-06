<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('telegram_download_jobs', function (Blueprint $table) {
        $table->id();
        $table->string('account_name');
        $table->foreignId('message_id')->constrained('telegram_messages');
        $table->string('status')->default('pending'); // pending, processing, completed, failed
        $table->text('error')->nullable();
        $table->integer('attempts')->default(0);
        $table->string('file_path')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_download_jobs');
    }
};
