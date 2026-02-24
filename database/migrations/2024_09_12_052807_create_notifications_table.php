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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->text('user_id')->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->enum('type', ['broadcast','payment_status'])->default('broadcast');
            $table->text('image')->nullable();
            $table->dateTime('scheduled_at')->useCurrent();
            $table->boolean('is_multicast')->default(false);
            $table->boolean('read')->default(false);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('url')->nullable();
            $table->string('screen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */ 
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
