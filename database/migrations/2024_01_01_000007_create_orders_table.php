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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('message_id');
            $table->bigInteger('chat_id');
            $table->bigInteger('user_id');
            $table->string('user_name')->nullable();
            $table->string('article')->nullable();
            $table->string('color')->nullable();
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit')->default('метр');
            $table->text('original_text');
            $table->string('status')->default('new'); // new, confirmed, processing, done
            $table->json('detected_items')->nullable();
            $table->float('confidence')->nullable();
            $table->timestamp('order_date');
            $table->timestamps();
            
            $table->index('status');
            $table->index('order_date');
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
        Schema::dropIfExists('orders');
    }
};