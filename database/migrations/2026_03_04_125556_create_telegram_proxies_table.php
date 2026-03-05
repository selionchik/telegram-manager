<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_proxies', function (Blueprint $table) {
            $table->id();
            $table->string('server');
            $table->integer('port');
            $table->string('secret');
            $table->string('type')->default('simple'); // fake_tls, simple, unknown
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('fail_count')->default(0);
            $table->float('response_time')->nullable();
            $table->float('success_rate')->nullable();
            $table->string('last_speed_rating')->nullable();
            $table->timestamps();
            
            $table->unique(['server', 'port', 'secret']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_proxies');
    }
};