<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\JadwalKuliah;
use App\Models\KebijakanBebanSks;
use App\Models\KelasPerkuliahan;
use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\SemesterAkademik;
use App\Models\SkalaNilai;
use Illuminate\Database\Seeder;

class KampusSeeder extends Seeder
{
    public function run(): void
    {
        $fakultasData = [
            'FT' => 'Fakultas Teknik',
            'FEB' => 'Fakultas Ekonomi dan Bisnis',
            'FIK' => 'Fakultas Ilmu Komputer',
        ];

        $fakultas = [];

        foreach ($fakultasData as $kode => $nama) {
            $fakultas[$kode] = Fakultas::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama],
            );
        }

        $programStudiData = [
            'FT-TI' => ['nama' => 'Teknik Industri', 'jenjang' => 'S1', 'fakultas' => 'FT'],
            'FEB-MNJ' => ['nama' => 'Manajemen', 'jenjang' => 'S1', 'fakultas' => 'FEB'],
            'FIK-IF' => ['nama' => 'Informatika', 'jenjang' => 'S1', 'fakultas' => 'FIK'],
        ];
        $programByFakultas = ['FT' => 'FT-TI', 'FEB' => 'FEB-MNJ', 'FIK' => 'FIK-IF'];

        $programStudi = [];

        foreach ($programStudiData as $kode => $data) {
            $programStudi[$kode] = ProgramStudi::updateOrCreate(
                ['kode' => $kode],
                [
                    'fakultas_id' => $fakultas[$data['fakultas']]->id,
                    'nama' => $data['nama'],
                    'jenjang' => $data['jenjang'],
                ],
            );
        }

        $dosenData = [
            ['nid' => 'D20260001', 'nama' => 'Dr. Budi Hartono', 'email' => 'budi.hartono@example.test', 'program_studi' => 'FT-TI'],
            ['nid' => 'D20260002', 'nama' => 'Dr. Maya Putri', 'email' => 'maya.putri@example.test', 'program_studi' => 'FEB-MNJ'],
            ['nid' => 'D20260003', 'nama' => 'Dr. Rina Kurnia', 'email' => 'rina.kurnia@example.test', 'program_studi' => 'FIK-IF'],
        ];

        $dosen = [];

        foreach ($dosenData as $data) {
            $dosen[$data['program_studi']] = Dosen::updateOrCreate(
                ['nid' => $data['nid']],
                [
                    'program_studi_id' => $programStudi[$data['program_studi']]->id,
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                ],
            );
        }

        $periode = SemesterAkademik::updateOrCreate(
            ['kode' => '2026/2027-1'],
            [
                'tahun_akademik' => '2026/2027',
                'semester' => 'ganjil',
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-12-31',
                'krs_dibuka_pada' => '2026-07-01 00:00:00',
                'krs_ditutup_pada' => '2026-08-31 23:59:59',
                'jumlah_pertemuan_standar' => 16,
                'persentase_minimum_kehadiran_uas' => 75,
            ],
        );

        foreach (['mahasiswa', 'dosen', 'admin', 'kaprodi'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $sksPolicies = [
            ['ips_minimum' => 0, 'ips_maksimum' => 1.49, 'sks_maksimum' => 12],
            ['ips_minimum' => 1.50, 'ips_maksimum' => 1.99, 'sks_maksimum' => 15],
            ['ips_minimum' => 2, 'ips_maksimum' => 2.49, 'sks_maksimum' => 18],
            ['ips_minimum' => 2.50, 'ips_maksimum' => 2.99, 'sks_maksimum' => 21],
            ['ips_minimum' => 3, 'ips_maksimum' => 4, 'sks_maksimum' => 24],
        ];

        foreach ($sksPolicies as $policy) {
            KebijakanBebanSks::updateOrCreate(
                ['ips_minimum' => $policy['ips_minimum'], 'ips_maksimum' => $policy['ips_maksimum']],
                ['sks_maksimum' => $policy['sks_maksimum']],
            );
        }

        foreach ([
            ['nilai_huruf' => 'A', 'nilai_minimum' => 80, 'nilai_maksimum' => 100, 'bobot_mutu' => 4],
            ['nilai_huruf' => 'B', 'nilai_minimum' => 70, 'nilai_maksimum' => 79.99, 'bobot_mutu' => 3],
            ['nilai_huruf' => 'C', 'nilai_minimum' => 60, 'nilai_maksimum' => 69.99, 'bobot_mutu' => 2],
            ['nilai_huruf' => 'D', 'nilai_minimum' => 50, 'nilai_maksimum' => 59.99, 'bobot_mutu' => 1],
            ['nilai_huruf' => 'E', 'nilai_minimum' => 0, 'nilai_maksimum' => 49.99, 'bobot_mutu' => 0],
        ] as $scale) {
            SkalaNilai::updateOrCreate(
                ['nilai_huruf' => $scale['nilai_huruf']],
                [
                    'nilai_minimum' => $scale['nilai_minimum'],
                    'nilai_maksimum' => $scale['nilai_maksimum'],
                    'bobot_mutu' => $scale['bobot_mutu'],
                ],
            );
        }

        $mahasiswaData = [
            ['nim' => '2026001001', 'nama' => 'Andi Pratama', 'email' => 'andi.pratama@example.test', 'angkatan' => 2026, 'fakultas' => 'FT'],
            ['nim' => '2026001002', 'nama' => 'Siti Rahmawati', 'email' => 'siti.rahmawati@example.test', 'angkatan' => 2026, 'fakultas' => 'FT'],
            ['nim' => '2026002001', 'nama' => 'Budi Santoso', 'email' => 'budi.santoso@example.test', 'angkatan' => 2026, 'fakultas' => 'FEB'],
            ['nim' => '2026002002', 'nama' => 'Dewi Lestari', 'email' => 'dewi.lestari@example.test', 'angkatan' => 2026, 'fakultas' => 'FEB'],
            ['nim' => '2026003001', 'nama' => 'Rizky Maulana', 'email' => 'rizky.maulana@example.test', 'angkatan' => 2026, 'fakultas' => 'FIK'],
            ['nim' => '2026003002', 'nama' => 'Nadia Putri', 'email' => 'nadia.putri@example.test', 'angkatan' => 2026, 'fakultas' => 'FIK'],
        ];

        $mahasiswa = [];

        foreach ($mahasiswaData as $data) {
            $mahasiswa[$data['nim']] = Mahasiswa::updateOrCreate(
                ['nim' => $data['nim']],
                [
                    'fakultas_id' => $fakultas[$data['fakultas']]->id,
                    'program_studi_id' => $programStudi[$programByFakultas[$data['fakultas']]]->id,
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                    'angkatan' => $data['angkatan'],
                    'dosen_pa_id' => $dosen[$programByFakultas[$data['fakultas']]]->id,
                ],
            );
        }

        $mataKuliahData = [
            ['kode' => 'TIF101', 'nama' => 'Pengantar Teknik', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FT'],
            ['kode' => 'TIF102', 'nama' => 'Matematika Teknik', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FT'],
            ['kode' => 'TIF103', 'nama' => 'Fisika Dasar', 'sks' => 2, 'semester' => 1, 'fakultas' => 'FT'],
            ['kode' => 'EKO101', 'nama' => 'Pengantar Ekonomi', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FEB'],
            ['kode' => 'EKO102', 'nama' => 'Pengantar Akuntansi', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FEB'],
            ['kode' => 'EKO103', 'nama' => 'Matematika Ekonomi', 'sks' => 2, 'semester' => 1, 'fakultas' => 'FEB'],
            ['kode' => 'IF101', 'nama' => 'Algoritma dan Pemrograman', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FIK'],
            ['kode' => 'IF102', 'nama' => 'Dasar Sistem Komputer', 'sks' => 3, 'semester' => 1, 'fakultas' => 'FIK'],
            ['kode' => 'IF103', 'nama' => 'Matematika Diskrit', 'sks' => 2, 'semester' => 1, 'fakultas' => 'FIK'],
        ];

        $mataKuliah = [];

        foreach ($mataKuliahData as $data) {
            $mataKuliah[$data['kode']] = MataKuliah::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'fakultas_id' => $fakultas[$data['fakultas']]->id,
                    'program_studi_id' => $programStudi[$programByFakultas[$data['fakultas']]]->id,
                    'nama' => $data['nama'],
                    'sks' => $data['sks'],
                    'semester' => $data['semester'],
                ],
            );
        }

        $roomByProgram = [];
        foreach ($programStudiData as $kode => $data) {
            $roomByProgram[$kode] = Ruangan::updateOrCreate(
                ['kode' => "{$kode}-R1"],
                [
                    'nama' => "Ruang {$data['nama']} 1",
                    'kapasitas' => 40,
                    'lokasi' => "Gedung {$data['fakultas']}",
                ],
            );
        }

        $classByCourse = [];
        foreach ($mataKuliahData as $index => $data) {
            $programCode = $programByFakultas[$data['fakultas']];
            $classByCourse[$data['kode']] = KelasPerkuliahan::updateOrCreate(
                [
                    'semester_akademik_id' => $periode->id,
                    'mata_kuliah_id' => $mataKuliah[$data['kode']]->id,
                    'kode_kelas' => 'A',
                ],
                [
                    'dosen_id' => $dosen[$programCode]->id,
                    'kuota' => 40,
                    'status' => 'open',
                ],
            );

            $day = ['senin', 'rabu', 'jumat'][$index % 3];
            $startHour = ['08:00', '10:00', '13:00'][$index % 3];
            JadwalKuliah::updateOrCreate(
                [
                    'kelas_perkuliahan_id' => $classByCourse[$data['kode']]->id,
                    'hari' => $day,
                    'jam_mulai' => $startHour,
                ],
                [
                    'ruangan_id' => $roomByProgram[$programCode]->id,
                    'jam_selesai' => date('H:i:s', strtotime($startHour) + 5400),
                ],
            );

            foreach ([
                ['nama' => 'Tugas', 'bobot' => 30],
                ['nama' => 'UTS', 'bobot' => 30],
                ['nama' => 'UAS', 'bobot' => 40],
            ] as $component) {
                KomponenNilai::updateOrCreate(
                    [
                        'kelas_perkuliahan_id' => $classByCourse[$data['kode']]->id,
                        'nama' => $component['nama'],
                    ],
                    ['bobot' => $component['bobot']],
                );
            }
        }

        $pengambilanMataKuliah = [
            '2026001001' => ['TIF101', 'TIF102', 'TIF103'],
            '2026001002' => ['TIF101', 'TIF102'],
            '2026002001' => ['EKO101', 'EKO102', 'EKO103'],
            '2026002002' => ['EKO101', 'EKO102'],
            '2026003001' => ['IF101', 'IF102', 'IF103'],
            '2026003002' => ['IF101', 'IF102'],
        ];

        foreach ($pengambilanMataKuliah as $nim => $kodeMataKuliah) {
            $krs = Krs::updateOrCreate(
                [
                    'mahasiswa_id' => $mahasiswa[$nim]->id,
                    'tahun_akademik' => '2026/2027',
                    'semester' => 'Ganjil',
                ],
                ['semester_akademik_id' => $periode->id, 'status' => 'approved'],
            );

            foreach ($kodeMataKuliah as $kode) {
                KrsDetail::updateOrCreate(
                    [
                        'krs_id' => $krs->id,
                        'mata_kuliah_id' => $mataKuliah[$kode]->id,
                    ],
                    ['kelas_perkuliahan_id' => $classByCourse[$kode]->id],
                );
            }
        }
    }
}
