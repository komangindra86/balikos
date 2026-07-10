<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penghunis', function (Blueprint $table) {
            $table->unsignedTinyInteger('jatuh_tempo_hari')->nullable()->after('tanggal_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('penghunis', function (Blueprint $table) {
            $table->dropColumn('jatuh_tempo_hari');
        });
    }
};
