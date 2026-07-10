<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihans', function (Blueprint $table) {
            $table->unsignedBigInteger('biaya_platform')->default(0)->after('nominal');
            $table->unsignedBigInteger('total_dibayar')->nullable()->after('biaya_platform');
        });

        Schema::create('kos_wallet_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('kos_wallets')->cascadeOnDelete();
            $table->unsignedBigInteger('nominal');
            $table->string('nama_bank');
            $table->string('nomor_rekening');
            $table->string('atas_nama');
            $table->enum('status', ['menunggu', 'diproses', 'selesai', 'ditolak'])->default('menunggu')->index();
            $table->text('catatan')->nullable();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kos_wallet_withdrawals');

        Schema::table('tagihans', function (Blueprint $table) {
            $table->dropColumn(['biaya_platform', 'total_dibayar']);
        });
    }
};
