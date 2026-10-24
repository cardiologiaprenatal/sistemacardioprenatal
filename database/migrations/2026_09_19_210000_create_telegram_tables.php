<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_user_id', 64)->unique();
            $table->string('username', 64)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id', 64)->unique();
            $table->string('type', 32)->nullable();
            $table->string('telegram_user_id', 64)->nullable();
            $table->timestamps();

            $table->index('telegram_user_id');
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->nullable()->unique();
            $table->string('telegram_user_id', 64)->nullable();
            $table->string('telegram_chat_id', 64);
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->string('username', 64)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->text('text')->nullable();
            $table->string('message_type', 32);
            $table->string('direction', 16);
            $table->timestamp('telegram_date')->nullable();
            $table->timestamps();

            $table->index('telegram_user_id');
            $table->index(['telegram_chat_id', 'telegram_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_chats');
        Schema::dropIfExists('telegram_users');
    }
};
