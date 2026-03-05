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
        Schema::create('telegram_actions', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('action'); // send_message, edit_post, delete_message
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processing, done, failed
            $table->text('error')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
        });
        
        // Стандартная таблица для Laravel jobs (если используете database queue)
        Schema::create('telegram_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_actions');
        Schema::dropIfExists('telegram_jobs');
    }
};