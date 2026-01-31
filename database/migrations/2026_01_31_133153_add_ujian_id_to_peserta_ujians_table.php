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
        Schema::table('peserta_ujians', function (Blueprint $table) {
            $table->foreignId('ujian_id')->nullable()->constrained('ujians')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peserta_ujians', function (Blueprint $table) {
            $table->dropForeign(['ujian_id']);
            $table->dropColumn('ujian_id');
        });
    }
};
