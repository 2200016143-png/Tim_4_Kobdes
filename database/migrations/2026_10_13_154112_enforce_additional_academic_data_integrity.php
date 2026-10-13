<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->text('alamat')->nullable();
            $table->date('tanggal_lahir')->nullable();
        });

        Schema::table('jadwal_kuliahs', function (Blueprint $table) {
            $table->unique(
                ['kelas_perkuliahan_id', 'hari', 'jam_mulai'],
                'jadwal_kuliahs_class_day_start_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_kuliahs', function (Blueprint $table) {
            $table->dropUnique('jadwal_kuliahs_class_day_start_unique');
        });

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'tanggal_lahir']);
        });
    }
};
