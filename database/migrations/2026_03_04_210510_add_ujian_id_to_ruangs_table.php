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
        Schema::table('ruangs', function (Blueprint $table) {
            $table->foreignId('ujian_id')->nullable()->after('id')->constrained('ujians')->onDelete('cascade');

            // Drop old unique constraint
            $table->dropUnique(['nama_ruang', 'kampus']);

            // Add new unique constraint including ujian_id
            $table->unique(['ujian_id', 'nama_ruang', 'kampus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ruangs', function (Blueprint $table) {
            $table->dropUnique(['ujian_id', 'nama_ruang', 'kampus']);
            $table->dropForeign(['ujian_id']);
            $table->dropColumn('ujian_id');

            // Restore old unique constraint
            $table->unique(['nama_ruang', 'kampus']);
        });
    }
};
