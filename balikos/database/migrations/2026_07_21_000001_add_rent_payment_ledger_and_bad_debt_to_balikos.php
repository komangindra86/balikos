<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagihan_pembayarans')) {
            Schema::create('tagihan_pembayarans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tagihan_id')->constrained('tagihans')->cascadeOnDelete();
                $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
                $table->foreignId('penghuni_id')->constrained('penghunis')->cascadeOnDelete();
                $table->unsignedBigInteger('nominal');
                $table->date('tanggal_bayar')->index();
                $table->string('metode_pembayaran')->nullable();
                $table->string('sumber')->default('manual');
                $table->text('catatan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['kos_id', 'tanggal_bayar']);
                $table->index(['tagihan_id', 'sumber']);
            });
        }

        Schema::table('tagihans', function (Blueprint $table) {
            if (! Schema::hasColumn('tagihans', 'kerugian_tunggakan')) {
                $table->unsignedBigInteger('kerugian_tunggakan')->default(0)->after('nominal_terbayar');
            }
            if (! Schema::hasColumn('tagihans', 'tanggal_kerugian')) {
                $table->date('tanggal_kerugian')->nullable()->after('kerugian_tunggakan');
            }
        });

        // Preserve existing payment data as one historical receipt per bill.
        DB::table('tagihans')
            ->where('nominal_terbayar', '>', 0)
            ->orderBy('id')
            ->eachById(function (object $bill): void {
                if (DB::table('tagihan_pembayarans')->where('tagihan_id', $bill->id)->where('sumber', 'riwayat_lama')->exists()) {
                    return;
                }

                DB::table('tagihan_pembayarans')->insert([
                    'tagihan_id' => $bill->id,
                    'kos_id' => $bill->kos_id,
                    'penghuni_id' => $bill->penghuni_id,
                    'nominal' => $bill->nominal_terbayar,
                    'tanggal_bayar' => $bill->tanggal_bayar ?: now()->toDateString(),
                    'metode_pembayaran' => $bill->metode_pembayaran,
                    'sumber' => 'riwayat_lama',
                    'catatan' => 'Riwayat pembayaran sebelum pencatatan pembayaran per transaksi diaktifkan.',
                    'created_at' => $bill->created_at ?: now(),
                    'updated_at' => now(),
                ]);
            });

        // A balance that remains when an occupant is checked out is recognized as a loss.
        DB::table('tagihans')
            ->join('penghunis', 'penghunis.id', '=', 'tagihans.penghuni_id')
            ->where('penghunis.status', 'keluar')
            ->whereIn('tagihans.status', ['belum_lunas', 'terlambat', 'ditolak'])
            ->orderBy('tagihans.id')
            ->select('tagihans.*', 'penghunis.tanggal_keluar')
            ->get()
            ->each(function (object $bill): void {
                $remaining = max(0, (int) $bill->nominal - (int) $bill->nominal_terbayar);
                if ($remaining === 0) {
                    return;
                }

                DB::table('tagihans')->where('id', $bill->id)->update([
                    'kerugian_tunggakan' => $remaining,
                    'tanggal_kerugian' => $bill->tanggal_keluar ?: now()->toDateString(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('tagihans', function (Blueprint $table) {
            $table->dropColumn(['kerugian_tunggakan', 'tanggal_kerugian']);
        });

        Schema::dropIfExists('tagihan_pembayarans');
    }
};
