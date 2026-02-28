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
            $table->unsignedBigInteger('pengawas_pengganti_id')->nullable()->after('pengawas_id');
            // Adding foreign key conceptually (or physically if strict schema)
            $table->foreign('pengawas_pengganti_id')->references('id')->on('pengawas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropForeign(['pengawas_pengganti_id']);
            $table->dropColumn('pengawas_pengganti_id');
        });
    }
};
