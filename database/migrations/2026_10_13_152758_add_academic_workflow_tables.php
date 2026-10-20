<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('program_studis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')->constrained()->restrictOnDelete();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->string('jenjang', 10);
            $table->timestamps();
        });

        Schema::create('dosens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('program_studi_id')->constrained()->restrictOnDelete();
            $table->string('nid', 20)->unique();
            $table->string('nama', 100);
            $table->string('email', 100)->unique();
            $table->string('no_hp', 15)->nullable();
            $table->timestamps();
        });

        Schema::create('ruangans', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->unsignedInteger('kapasitas');
            $table->string('lokasi', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('semester_akademiks', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 12)->unique();
            $table->string('tahun_akademik', 9);
            $table->string('semester', 10);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->dateTime('krs_dibuka_pada')->nullable();
            $table->dateTime('krs_ditutup_pada')->nullable();
            $table->unsignedTinyInteger('jumlah_pertemuan_standar')->default(16);
            $table->decimal('persentase_minimum_kehadiran_uas', 5, 2)->default(75);
            $table->timestamps();

            $table->unique(['tahun_akademik', 'semester']);
        });

        Schema::create('kurikulums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained()->restrictOnDelete();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('tahun_mulai');
            $table->unsignedSmallInteger('tahun_selesai')->nullable();
            $table->timestamps();
        });

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('dosen_pa_id')->nullable()->constrained('dosens')->nullOnDelete();
        });

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('semester_rekomendasi');
            $table->boolean('wajib')->default(true);
            $table->timestamps();

            $table->unique(['kurikulum_id', 'mata_kuliah_id']);
        });

        Schema::create('prasyarat_mata_kuliahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prasyarat_id')->constrained('mata_kuliahs')->restrictOnDelete();
            $table->string('nilai_minimum', 1)->default('D');
            $table->timestamps();

            $table->unique(['mata_kuliah_id', 'prasyarat_id']);
        });

        Schema::create('kelas_perkuliahans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained()->restrictOnDelete();
            $table->foreignId('dosen_id')->constrained('dosens')->restrictOnDelete();
            $table->foreignId('semester_akademik_id')->constrained()->restrictOnDelete();
            $table->string('kode_kelas', 20);
            $table->unsignedInteger('kuota');
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(
                ['semester_akademik_id', 'mata_kuliah_id', 'kode_kelas'],
                'kelas_semester_course_section_unique',
            );
        });

        Schema::create('jadwal_kuliahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_perkuliahan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ruangan_id')->constrained()->restrictOnDelete();
            $table->string('hari', 10);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->timestamps();

            $table->index(['ruangan_id', 'hari', 'jam_mulai']);
            $table->index(['kelas_perkuliahan_id', 'hari', 'jam_mulai']);
        });

        Schema::create('kebijakan_beban_sks', function (Blueprint $table) {
            $table->id();
            $table->decimal('ips_minimum', 3, 2);
            $table->decimal('ips_maksimum', 3, 2);
            $table->unsignedTinyInteger('sks_maksimum');
            $table->timestamps();

            $table->unique(['ips_minimum', 'ips_maksimum']);
        });

        Schema::create('skala_nilais', function (Blueprint $table) {
            $table->id();
            $table->string('nilai_huruf', 1)->unique();
            $table->decimal('nilai_minimum', 5, 2);
            $table->decimal('nilai_maksimum', 5, 2);
            $table->decimal('bobot_mutu', 3, 2);
            $table->timestamps();
        });

        Schema::table('krs', function (Blueprint $table) {
            $table->foreignId('semester_akademik_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('disetujui_oleh_dosen_id')->nullable()->constrained('dosens')->nullOnDelete();
            $table->dateTime('diajukan_pada')->nullable();
            $table->dateTime('disetujui_pada')->nullable();
            $table->text('catatan_penolakan')->nullable();
        });

        Schema::table('krs_details', function (Blueprint $table) {
            $table->foreignId('kelas_perkuliahan_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('nilai_akhir_angka', 5, 2)->nullable();
            $table->string('nilai_huruf', 1)->nullable();
        });

        Schema::create('komponen_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_perkuliahan_id')->constrained()->cascadeOnDelete();
            $table->string('nama', 50);
            $table->decimal('bobot', 5, 2);
            $table->timestamps();

            $table->unique(['kelas_perkuliahan_id', 'nama']);
        });

        Schema::create('krs_detail_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilais')->restrictOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->timestamps();

            $table->unique(['krs_detail_id', 'komponen_nilai_id']);
        });

        Schema::create('pertemuan_kuliahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_perkuliahan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('nomor_pertemuan');
            $table->date('tanggal');
            $table->string('topik')->nullable();
            $table->string('status_presensi_dosen', 10)->nullable();
            $table->timestamps();

            $table->unique(['kelas_perkuliahan_id', 'nomor_pertemuan']);
        });

        Schema::create('presensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pertemuan_kuliah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('krs_detail_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->dateTime('dicatat_pada');
            $table->timestamps();

            $table->unique(['pertemuan_kuliah_id', 'krs_detail_id']);
        });

        Schema::create('riwayat_perubahan_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')->constrained()->restrictOnDelete();
            $table->foreignId('dosen_id')->constrained('dosens')->restrictOnDelete();
            $table->decimal('nilai_angka_lama', 5, 2)->nullable();
            $table->string('nilai_huruf_lama', 1)->nullable();
            $table->decimal('nilai_angka_baru', 5, 2)->nullable();
            $table->string('nilai_huruf_baru', 1)->nullable();
            $table->text('alasan');
            $table->dateTime('diubah_pada');
            $table->timestamps();
        });

        DB::table('krs')->where('status', 'disetujui')->update(['status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_perubahan_nilais');
        Schema::dropIfExists('presensis');
        Schema::dropIfExists('pertemuan_kuliahs');
        Schema::dropIfExists('krs_detail_nilais');
        Schema::dropIfExists('komponen_nilais');

        Schema::table('krs_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_perkuliahan_id');
            $table->dropColumn(['nilai_akhir_angka', 'nilai_huruf']);
        });

        Schema::table('krs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_akademik_id');
            $table->dropConstrainedForeignId('disetujui_oleh_dosen_id');
            $table->dropColumn(['diajukan_pada', 'disetujui_pada', 'catatan_penolakan']);
        });

        Schema::dropIfExists('skala_nilais');
        Schema::dropIfExists('kebijakan_beban_sks');
        Schema::dropIfExists('jadwal_kuliahs');
        Schema::dropIfExists('kelas_perkuliahans');
        Schema::dropIfExists('prasyarat_mata_kuliahs');
        Schema::dropIfExists('kurikulum_mata_kuliah');

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_studi_id');
        });

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dosen_pa_id');
            $table->dropConstrainedForeignId('program_studi_id');
        });

        Schema::dropIfExists('kurikulums');
        Schema::dropIfExists('semester_akademiks');
        Schema::dropIfExists('ruangans');
        Schema::dropIfExists('dosens');
        Schema::dropIfExists('program_studis');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        DB::table('krs')->where('status', 'approved')->update(['status' => 'disetujui']);
    }
};
