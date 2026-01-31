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
        Schema::create('laporan_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->cascadeOnDelete();

            $table->foreignId('pengawas_id')->constrained('pengawas')->cascadeOnDelete();

            $table->foreignId('mapel_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->dateTime('mulai_ujian');
            $table->dateTime('ujian_berakhir');
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');

            $table->integer('total_expected');
            $table->integer('total_present');
            $table->integer('total_absent');

            $table->text('absent_details')->nullable();
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_ujians');
    }
};
