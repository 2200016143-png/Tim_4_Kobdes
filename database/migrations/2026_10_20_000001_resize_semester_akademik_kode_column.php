<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semester_akademiks', function (Blueprint $table) {
            $table->string('kode', 12)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('semester_akademiks')->whereRaw('CHAR_LENGTH(kode) > 10')->exists()) {
            throw new RuntimeException('Semester codes longer than 10 characters cannot be safely truncated.');
        }

        Schema::table('semester_akademiks', function (Blueprint $table) {
            $table->string('kode', 10)->change();
        });
    }
};
