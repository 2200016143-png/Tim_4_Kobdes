<?php

namespace Tests\Feature;

use App\Models\KrsDetail;
use App\Services\AttendanceService;
use Database\Seeders\LaporanDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_seeder_creates_at_least_100000_linked_academic_rows_and_is_idempotent(): void
    {
        $this->seed(LaporanDataSeeder::class);

        $this->assertDatabaseCount('mahasiswas', 2_006);
        $this->assertDatabaseCount('krs', 2_006);
        $this->assertDatabaseCount('krs_details', 6_015);
        $this->assertDatabaseCount('kelas_perkuliahans', 162);
        $this->assertDatabaseCount('jadwal_kuliahs', 162);
        $this->assertDatabaseCount('komponen_nilais', 486);
        $this->assertDatabaseCount('pertemuan_kuliahs', 2_592);
        $this->assertDatabaseCount('presensis', 96_000);
        $this->assertDatabaseCount('krs_detail_nilais', 18_000);

        $generatedRows = (DB::table('mahasiswas')->count() - 6)
            + (DB::table('krs')->count() - 6)
            + (DB::table('krs_details')->count() - 15)
            + DB::table('presensis')->count()
            + DB::table('krs_detail_nilais')->count();

        $this->assertGreaterThanOrEqual(100_000, $generatedRows);
        $this->assertSame(124_000, $generatedRows);

        $student = DB::table('mahasiswas')->where('nim', '2020000001')->first();
        $this->assertNotNull($student);

        $krs = DB::table('krs')->where('mahasiswa_id', $student->id)->first();
        $this->assertSame('approved', $krs->status);

        $detail = DB::table('krs_details')
            ->where('krs_id', $krs->id)
            ->whereNotNull('kelas_perkuliahan_id')
            ->first();
        $this->assertNotNull($detail);
        $this->assertNotNull($detail->nilai_akhir_angka);
        $this->assertNotNull($detail->nilai_huruf);
        $this->assertSame(16, DB::table('presensis')->where('krs_detail_id', $detail->id)->count());
        $this->assertSame(13, DB::table('presensis')
            ->where('krs_detail_id', $detail->id)
            ->where('status', 'hadir')
            ->count());
        $this->assertSame(3, DB::table('krs_detail_nilais')->where('krs_detail_id', $detail->id)->count());
        $this->assertTrue(app(AttendanceService::class)->isEligibleForUas(
            KrsDetail::query()->findOrFail($detail->id),
        ));

        $overCapacityClasses = DB::table('krs_details')
            ->join('krs', 'krs.id', '=', 'krs_details.krs_id')
            ->whereIn('krs.status', ['draft', 'submitted', 'approved'])
            ->groupBy('krs_details.kelas_perkuliahan_id')
            ->havingRaw('COUNT(*) > 40')
            ->count();
        $this->assertSame(0, $overCapacityClasses);

        $conflictingSchedules = DB::table('jadwal_kuliahs as first_schedule')
            ->join('jadwal_kuliahs as second_schedule', function ($join): void {
                $join->on('first_schedule.id', '<', 'second_schedule.id')
                    ->on('first_schedule.hari', '=', 'second_schedule.hari')
                    ->on('first_schedule.jam_mulai', '<', 'second_schedule.jam_selesai')
                    ->on('first_schedule.jam_selesai', '>', 'second_schedule.jam_mulai');
            })
            ->join('kelas_perkuliahans as first_class', 'first_class.id', '=', 'first_schedule.kelas_perkuliahan_id')
            ->join('kelas_perkuliahans as second_class', 'second_class.id', '=', 'second_schedule.kelas_perkuliahan_id')
            ->whereColumn('first_class.semester_akademik_id', 'second_class.semester_akademik_id')
            ->where(function ($query): void {
                $query->whereColumn('first_schedule.ruangan_id', 'second_schedule.ruangan_id')
                    ->orWhereColumn('first_class.dosen_id', 'second_class.dosen_id');
            })
            ->count();
        $this->assertSame(0, $conflictingSchedules);

        $this->seed(LaporanDataSeeder::class);

        $this->assertDatabaseCount('mahasiswas', 2_006);
        $this->assertDatabaseCount('krs', 2_006);
        $this->assertDatabaseCount('krs_details', 6_015);
        $this->assertDatabaseCount('kelas_perkuliahans', 162);
        $this->assertDatabaseCount('jadwal_kuliahs', 162);
        $this->assertDatabaseCount('komponen_nilais', 486);
        $this->assertDatabaseCount('pertemuan_kuliahs', 2_592);
        $this->assertDatabaseCount('presensis', 96_000);
        $this->assertDatabaseCount('krs_detail_nilais', 18_000);
    }
}
