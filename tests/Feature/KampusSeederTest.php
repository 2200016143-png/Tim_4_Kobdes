<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Mahasiswa;
use Database\Seeders\KampusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KampusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_kampus_seeder_creates_linked_faculty_students_and_krs_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('fakultas', 3);
        $this->assertDatabaseCount('mahasiswas', 6);
        $this->assertDatabaseCount('mata_kuliahs', 9);
        $this->assertDatabaseCount('krs', 6);
        $this->assertDatabaseCount('krs_details', 15);
        $this->assertDatabaseCount('ruangans', 3);
        $this->assertDatabaseCount('kelas_perkuliahans', 9);
        $this->assertDatabaseCount('jadwal_kuliahs', 9);
        $this->assertDatabaseCount('komponen_nilais', 27);

        $mahasiswa = Mahasiswa::with('fakultas', 'krs.details.mataKuliah')
            ->where('nim', '2026001001')
            ->firstOrFail();

        $this->assertSame('Fakultas Teknik', $mahasiswa->fakultas->nama);
        $this->assertCount(1, $mahasiswa->krs);
        $this->assertCount(3, $mahasiswa->krs->first()->details);
        $this->assertSame(
            8,
            $mahasiswa->krs->first()->details->sum(fn ($detail) => $detail->mataKuliah->sks),
        );
        $this->assertNotNull($mahasiswa->krs->first()->details->first()->kelas_perkuliahan_id);
        $this->assertSame(2, Fakultas::where('kode', 'FT')->firstOrFail()->mahasiswas()->count());
    }

    public function test_kampus_seeder_can_be_run_more_than_once_without_duplicates(): void
    {
        $this->seed(KampusSeeder::class);
        $this->seed(KampusSeeder::class);

        $this->assertDatabaseCount('fakultas', 3);
        $this->assertDatabaseCount('mahasiswas', 6);
        $this->assertDatabaseCount('mata_kuliahs', 9);
        $this->assertDatabaseCount('krs', 6);
        $this->assertDatabaseCount('krs_details', 15);
        $this->assertDatabaseCount('ruangans', 3);
        $this->assertDatabaseCount('kelas_perkuliahans', 9);
        $this->assertDatabaseCount('jadwal_kuliahs', 9);
        $this->assertDatabaseCount('komponen_nilais', 27);
    }
}
