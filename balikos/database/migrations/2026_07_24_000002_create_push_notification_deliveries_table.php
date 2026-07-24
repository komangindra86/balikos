<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_notification_token_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('receipt_id')->nullable()->unique();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('status')->index();
            $table->string('error_code')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_deliveries');
    }
};
