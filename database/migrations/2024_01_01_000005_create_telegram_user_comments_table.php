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
        Schema::create('telegram_user_comments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('user_name')->nullable();
            $table->bigInteger('chat_id');
            $table->string('chat_title');
            $table->bigInteger('post_id')->nullable();
            $table->bigInteger('comment_id');
            $table->text('text');
            $table->timestamp('date');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('date');
            $table->unique(['user_id', 'comment_id'], 'user_comment_unique');
            
            $table->foreign('chat_id')
                  ->references('id')
                  ->on('telegram_chats')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_user_comments');
    }
};