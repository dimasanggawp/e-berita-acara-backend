<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // For peserta_ujians: clear string column, add foreign key
        Schema::table('peserta_ujians', function (Blueprint $table) {
            $table->dropColumn('ruang');
        });
        Schema::table('peserta_ujians', function (Blueprint $table) {
            $table->unsignedBigInteger('ruang_id')->nullable()->after('kelas');
            $table->foreign('ruang_id')->references('id')->on('ruangs')->onDelete('set null');
        });

        // For jadwal_ujians: clear string column, add foreign key
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropColumn('ruang');
        });
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->unsignedBigInteger('ruang_id')->nullable()->after('pengawas_id');
            $table->foreign('ruang_id')->references('id')->on('ruangs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peserta_ujians', function (Blueprint $table) {
            $table->dropForeign(['ruang_id']);
            $table->dropColumn('ruang_id');
            $table->string('ruang')->nullable();
        });

        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropForeign(['ruang_id']);
            $table->dropColumn('ruang_id');
            $table->string('ruang')->nullable();
        });
    }
};
