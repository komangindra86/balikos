<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();
        });

        DB::table('kamars')
            ->whereNotNull('foto')
            ->where('foto', '!=', '')
            ->orderBy('id')
            ->get(['id', 'foto'])
            ->each(function ($room) {
                DB::table('kamar_fotos')->insert([
                    'kamar_id' => $room->id,
                    'path' => $room->foto,
                    'urutan' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar_fotos');
    }
};
