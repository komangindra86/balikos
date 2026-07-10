<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->enum('verification_mode', ['manual', 'automatic'])->default('manual')->after('jenis')->index();
            $table->string('gateway_provider')->nullable()->after('verification_mode')->index();
            $table->string('gateway_account_id')->nullable()->after('gateway_provider');
            $table->string('gateway_reference')->nullable()->after('gateway_account_id');
            $table->string('qris_url')->nullable()->after('qris_image');
        });

        Schema::create('kos_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->unique()->constrained('kos')->cascadeOnDelete();
            $table->unsignedBigInteger('saldo_tersedia')->default(0);
            $table->unsignedBigInteger('saldo_pending')->default(0);
            $table->unsignedBigInteger('total_ditarik')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kos_wallets');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'verification_mode',
                'gateway_provider',
                'gateway_account_id',
                'gateway_reference',
                'qris_url',
            ]);
        });
    }
};
