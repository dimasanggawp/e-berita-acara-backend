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
        Schema::create('presensi_pesertas', function (Blueprint $table) {
            $table->id();
            $table->string('kode_peserta');
            $table->foreignId('ujian_id')->nullable()->constrained('ujians')->onDelete('cascade');
            $table->foreignId('ruang_id')->nullable()->constrained('ruangs')->onDelete('cascade');
            $table->timestamp('waktu_datang')->nullable();
            $table->timestamp('waktu_pulang')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_pesertas');
    }
};
