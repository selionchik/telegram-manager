<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Если таблица уже есть, добавляем поля или создаём заново
        if (!Schema::hasTable('telegram_accounts')) {
            Schema::create('telegram_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // account1, account2
                $table->bigInteger('tg_id')->nullable(); // ID в Telegram
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('username')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('disconnected');
                $table->timestamps();
            });
        } else {
            // Добавляем недостающие поля, если таблица уже есть
            Schema::table('telegram_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('telegram_accounts', 'tg_id')) {
                    $table->bigInteger('tg_id')->nullable()->after('name');
                }
                if (!Schema::hasColumn('telegram_accounts', 'first_name')) {
                    $table->string('first_name')->nullable()->after('tg_id');
                }
                if (!Schema::hasColumn('telegram_accounts', 'last_name')) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('telegram_accounts', 'username')) {
                    $table->string('username')->nullable()->after('last_name');
                }
            });
        }
    }

    public function down(): void
    {
        // Ничего не делаем, чтобы не сломать существующую структуру
    }
};