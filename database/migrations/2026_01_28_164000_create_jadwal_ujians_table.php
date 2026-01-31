<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jadwal_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->onDelete('cascade');
            $table->foreignId('pengawas_id')->constrained('pengawas')->onDelete('cascade');

            $table->foreignId('mapel_id')->constrained('mata_pelajarans')->onDelete('cascade');
            $table->dateTime('mulai_ujian');
            $table->dateTime('ujian_berakhir');
            $table->integer('total_siswa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujians');
    }
};
