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
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->string('ruang')->nullable()->after('pengawas_id');
            $table->string('nama_mapel')->nullable()->after('ruang');
            $table->unsignedBigInteger('mapel_id')->nullable()->change();
            $table->integer('total_siswa')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropColumn(['ruang', 'nama_mapel']);
            $table->unsignedBigInteger('mapel_id')->nullable(false)->change();
            $table->integer('total_siswa')->nullable(false)->change();
        });
    }
};
