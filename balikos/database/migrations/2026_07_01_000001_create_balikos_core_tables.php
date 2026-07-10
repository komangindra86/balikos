<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama_kos');
            $table->text('alamat');
            $table->string('kecamatan')->index();
            $table->string('desa')->nullable()->index();
            $table->string('banjar')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('no_wa')->nullable();
            $table->text('aturan_kos')->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('kamars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->string('nomor_kamar');
            $table->string('tipe_kamar')->nullable();
            $table->unsignedBigInteger('harga_bulanan')->default(0);
            $table->enum('status', ['kosong', 'terisi', 'maintenance'])->default('kosong')->index();
            $table->boolean('fasilitas_ac')->default(false);
            $table->boolean('fasilitas_km_dalam')->default(false);
            $table->boolean('fasilitas_wifi')->default(false);
            $table->boolean('fasilitas_kasur')->default(false);
            $table->boolean('fasilitas_lemari')->default(false);
            $table->boolean('fasilitas_meja')->default(false);
            $table->boolean('fasilitas_parkir')->default(false);
            $table->string('foto')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['kos_id', 'nomor_kamar']);
        });

        Schema::create('penghunis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
            $table->string('nama_lengkap');
            $table->string('no_ktp')->nullable();
            $table->string('no_wa')->nullable();
            $table->text('alamat_asal')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('no_kendaraan')->nullable();
            $table->string('kontak_darurat')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->enum('status', ['aktif', 'keluar'])->default('aktif')->index();
            $table->unsignedBigInteger('active_kamar_id')->nullable();
            $table->string('portal_token', 80)->unique();
            $table->timestamps();
            $table->index(['kos_id', 'kamar_id', 'status']);
            $table->unique('active_kamar_id');
        });

        Schema::create('tagihans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
            $table->foreignId('penghuni_id')->constrained('penghunis')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedBigInteger('nominal');
            $table->date('tanggal_jatuh_tempo');
            $table->date('tanggal_bayar')->nullable();
            $table->enum('status', ['belum_lunas', 'menunggu_verifikasi', 'lunas', 'ditolak', 'terlambat'])->default('belum_lunas')->index();
            $table->string('metode_pembayaran')->nullable();
            $table->string('bukti_pembayaran')->nullable();
            $table->timestamp('tanggal_konfirmasi')->nullable();
            $table->timestamp('tanggal_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_penolakan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['penghuni_id', 'bulan', 'tahun']);
            $table->index(['kos_id', 'bulan', 'tahun', 'status']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->enum('jenis', ['bank', 'qris', 'tunai'])->index();
            $table->string('nama_bank')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('atas_nama')->nullable();
            $table->string('qris_image')->nullable();
            $table->text('instruksi_pembayaran')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('kategori_keuangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->string('nama_kategori');
            $table->enum('jenis', ['pemasukan', 'pengeluaran'])->index();
            $table->timestamps();
            $table->unique(['kos_id', 'nama_kategori', 'jenis']);
        });

        Schema::create('transaksi_keuangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori_keuangan')->nullOnDelete();
            $table->enum('jenis', ['pemasukan', 'pengeluaran'])->index();
            $table->date('tanggal')->index();
            $table->unsignedBigInteger('nominal');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengumuman_kos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->string('judul');
            $table->text('isi');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman_kos');
        Schema::dropIfExists('transaksi_keuangan');
        Schema::dropIfExists('kategori_keuangan');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('tagihans');
        Schema::dropIfExists('penghunis');
        Schema::dropIfExists('kamars');
        Schema::dropIfExists('kos');
    }
};
