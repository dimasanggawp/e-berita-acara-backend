<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add date column and unique constraint for presensi_pesertas
        Schema::table('presensi_pesertas', function (Blueprint $table) {
            // Add a virtual column for the date part of created_at
            DB::statement('ALTER TABLE presensi_pesertas ADD COLUMN created_date DATE AS (DATE(created_at)) STORED');
        });

        // Add unique index on kode_peserta and created_date
        Schema::table('presensi_pesertas', function (Blueprint $table) {
            $table->unique(['kode_peserta', 'created_date'], 'idx_presensi_peserta_unique_daily');
        });

        // Add date column and unique constraint for presensi_pengawas
        Schema::table('presensi_pengawas', function (Blueprint $table) {
            DB::statement('ALTER TABLE presensi_pengawas ADD COLUMN created_date DATE AS (DATE(created_at)) STORED');
        });

        Schema::table('presensi_pengawas', function (Blueprint $table) {
            $table->unique(['pengawas_id', 'created_date'], 'idx_presensi_pengawas_unique_daily');
        });

        // Add unique constraint for jadwal_ujians to prevent duplicate schedules
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->unique(
                ['ujian_id', 'pengawas_id', 'mapel_id', 'sesi_id', 'mulai_ujian'],
                'idx_jadwal_unique_schedule'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique constraints and generated columns
        Schema::table('presensi_pesertas', function (Blueprint $table) {
            $table->dropUnique('idx_presensi_peserta_unique_daily');
        });
        DB::statement('ALTER TABLE presensi_pesertas DROP COLUMN created_date');

        Schema::table('presensi_pengawas', function (Blueprint $table) {
            $table->dropUnique('idx_presensi_pengawas_unique_daily');
        });
        DB::statement('ALTER TABLE presensi_pengawas DROP COLUMN created_date');

        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropUnique('idx_jadwal_unique_schedule');
        });
    }
};
