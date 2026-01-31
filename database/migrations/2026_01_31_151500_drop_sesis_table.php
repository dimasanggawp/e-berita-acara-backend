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
        Schema::disableForeignKeyConstraints();

        // Drop from jadwal_ujians
        try {
            DB::statement('ALTER TABLE jadwal_ujians DROP FOREIGN KEY IF EXISTS jadwal_ujians_sesi_id_foreign');
        } catch (\Exception $e) {
        }
        try {
            DB::statement('ALTER TABLE jadwal_ujians DROP INDEX IF EXISTS idx_jadwal_unique_schedule');
        } catch (\Exception $e) {
        }
        try {
            DB::statement('ALTER TABLE jadwal_ujians DROP INDEX IF EXISTS jadwal_ujians_sesi_id_foreign');
        } catch (\Exception $e) {
        }

        // Drop from laporan_ujians
        try {
            DB::statement('ALTER TABLE laporan_ujians DROP FOREIGN KEY IF EXISTS laporan_ujians_sesi_id_foreign');
        } catch (\Exception $e) {
        }
        try {
            DB::statement('ALTER TABLE laporan_ujians DROP INDEX IF EXISTS laporan_ujians_sesi_id_foreign');
        } catch (\Exception $e) {
        }

        // Drop columns
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal_ujians', 'sesi_id')) {
                $table->dropColumn('sesi_id');
            }
        });

        Schema::table('laporan_ujians', function (Blueprint $table) {
            if (Schema::hasColumn('laporan_ujians', 'sesi_id')) {
                $table->dropColumn('sesi_id');
            }
        });

        // Recreate unique index without sesi_id
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->unique(
                ['ujian_id', 'pengawas_id', 'mapel_id', 'mulai_ujian'],
                'idx_jadwal_unique_schedule'
            );
        });

        // Finally drop the sesis table
        Schema::dropIfExists('sesis');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('sesis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sesi');
            $table->string('mulai');
            $table->string('selesai');
            $table->timestamps();
        });

        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->foreignId('sesi_id')->nullable()->constrained('sesis')->onDelete('cascade');
            // Re-drop the new unique index
            $table->dropUnique('idx_jadwal_unique_schedule');
        });

        Schema::table('jadwal_ujians', function (Blueprint $table) {
            // Restore original unique index
            $table->unique(
                ['ujian_id', 'pengawas_id', 'mapel_id', 'sesi_id', 'mulai_ujian'],
                'idx_jadwal_unique_schedule'
            );
        });

        Schema::table('laporan_ujians', function (Blueprint $table) {
            $table->foreignId('sesi_id')->nullable()->constrained('sesis')->cascadeOnDelete();
        });
    }
};
