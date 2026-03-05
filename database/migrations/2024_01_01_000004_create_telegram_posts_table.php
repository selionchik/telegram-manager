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
        Schema::create('telegram_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->string('title')->nullable();
            $table->text('content');
            $table->json('media')->nullable();
            $table->integer('views')->default(0);
            $table->integer('forwards')->default(0);
            $table->integer('replies_count')->default(0);
            $table->timestamp('posted_at');
            $table->boolean('edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            
            $table->unique(['chat_id', 'message_id']);
            $table->index('posted_at');
            
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
        Schema::dropIfExists('telegram_posts');
    }
};