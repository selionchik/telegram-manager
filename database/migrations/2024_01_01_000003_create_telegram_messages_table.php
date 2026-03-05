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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->bigInteger('from_id')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('date');
            $table->text('text')->nullable();
            $table->bigInteger('reply_to_msg_id')->nullable();
            $table->bigInteger('forward_from_chat_id')->nullable();
            $table->bigInteger('forward_from_message_id')->nullable();
            $table->boolean('out')->default(false);
            $table->boolean('has_media')->default(false);
            $table->json('media_info')->nullable();
            $table->json('raw_data')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamps();
            
            $table->unique(['chat_id', 'message_id']);
            $table->index('date');
            $table->index('from_id');
            $table->index('processed');
            
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
        Schema::dropIfExists('telegram_messages');
    }
};