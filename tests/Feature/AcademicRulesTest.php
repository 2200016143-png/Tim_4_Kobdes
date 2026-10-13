<?php

namespace Tests\Feature;

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\KelasPerkuliahan;
use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\PrasyaratMataKuliah;
use App\Models\ProgramStudi;
use App\Models\Ruangan;
use App\Models\SemesterAkademik;
use App\Services\AttendanceService;
use App\Services\GradeService;
use App\Services\KrsWorkflowService;
use App\Services\ScheduleService;
use Database\Seeders\KampusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicRulesTest extends TestCase
{
    use RefreshDatabase;

    private int $studentSequence = 0;

    public function test_krs_rejects_unmet_prerequisites_and_accepts_a_previously_passed_course(): void
    {
        $context = $this->academicContext();
        $course = MataKuliah::where('kode', 'IF102')->firstOrFail();
        $prerequisite = MataKuliah::where('kode', 'IF101')->firstOrFail();
        PrasyaratMataKuliah::create([
            'mata_kuliah_id' => $course->id,
            'prasyarat_id' => $prerequisite->id,
            'nilai_minimum' => 'D',
        ]);
        $class = $this->createClass($context, $course);
        $class->jadwals()->create([
            'ruangan_id' => $context['room']->id,
            'hari' => 'senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:00',
        ]);
        $workflow = new KrsWorkflowService;
        $previousKrs = Krs::create([
            'mahasiswa_id' => $context['student']->id,
            'semester_akademik_id' => SemesterAkademik::where('kode', '2026/2027-1')->value('id'),
            'tahun_akademik' => '2026/2027',
            'semester' => 'Ganjil',
            'status' => 'approved',
        ]);
        $previousDetail = $previousKrs->details()->create([
            'mata_kuliah_id' => $prerequisite->id,
            'nilai_huruf' => 'E',
        ]);

        $this->assertValidationError(
            fn () => $workflow->addClass($context['krs'], $class->id),
            'prasyarat',
            'Prasyarat IF101 harus lulus minimal dengan nilai D.',
        );

        $previousDetail->update(['nilai_huruf' => 'D']);

        $detail = $workflow->addClass($context['krs'], $class->id);

        $this->assertSame((int) $course->id, (int) $detail->mata_kuliah_id);
        $this->assertSame((int) $class->id, (int) $detail->kelas_perkuliahan_id);
    }

    public function test_krs_enforces_credit_limit_from_previous_semester_ips(): void
    {
        $context = $this->academicContext();
        $workflow = new KrsWorkflowService;
        $courses = collect([
            'IF101' => MataKuliah::where('kode', 'IF101')->firstOrFail(),
            'IF102' => MataKuliah::where('kode', 'IF102')->firstOrFail(),
        ]);

        foreach (['IF104', 'IF105', 'IF106'] as $index => $code) {
            $courses->put($code, MataKuliah::create([
                'fakultas_id' => $context['faculty']->id,
                'program_studi_id' => $context['program']->id,
                'kode' => $code,
                'nama' => "Mata Kuliah {$code}",
                'sks' => 3,
                'semester' => 1,
            ]));
        }

        foreach ($courses->values() as $index => $course) {
            $class = $this->createClass($context, $course);
            $start = 8 + $index * 2;
            $class->jadwals()->create([
                'ruangan_id' => $context['room']->id,
                'hari' => 'senin',
                'jam_mulai' => sprintf('%02d:00', $start),
                'jam_selesai' => sprintf('%02d:50', $start),
            ]);

            if ($index < 4) {
                $workflow->addClass($context['krs'], $class->id);
            } else {
                $this->assertValidationError(
                    fn () => $workflow->addClass($context['krs'], $class->id),
                    'sks',
                    'Beban KRS 15 SKS melebihi batas 12 SKS untuk IPS 0.',
                );
            }
        }

        $this->assertSame(4, $context['krs']->details()->count());
    }

    public function test_schedule_rejects_room_and_lecturer_overlaps_but_allows_adjacent_classes(): void
    {
        $context = $this->academicContext();
        $course = MataKuliah::where('kode', 'IF101')->firstOrFail();
        $firstClass = $this->createClass($context, $course);
        $secondClass = $this->createClass($context, MataKuliah::where('kode', 'IF102')->firstOrFail());
        $scheduleService = new ScheduleService;

        $scheduleService->schedule($firstClass->id, $context['room']->id, 'senin', '09:00', '10:00');
        $scheduleService->schedule($secondClass->id, $context['room']->id, 'senin', '10:00', '11:00');

        $this->assertValidationError(
            fn () => $scheduleService->schedule($secondClass->id, $context['room']->id, 'senin', '09:30', '10:30'),
            'jadwal',
            'Jadwal bertabrakan dengan jadwal dosen, kelas, atau ruangan pada semester yang sama.',
        );
    }

    public function test_dpa_can_approve_submitted_krs_only_for_an_assigned_student(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $class->jadwals()->create([
            'ruangan_id' => $context['room']->id,
            'hari' => 'senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:00',
        ]);
        $workflow = new KrsWorkflowService;
        $workflow->addClass($context['krs'], $class->id);
        $submitted = $workflow->submit($context['krs']);

        $this->assertSame('submitted', $submitted->status);

        $otherDosen = Dosen::create([
            'program_studi_id' => $context['program']->id,
            'nid' => 'DTEST999',
            'nama' => 'Dosen yang tidak ditugaskan',
            'email' => 'dosen.tidak-ditugaskan@example.test',
        ]);

        $this->assertValidationError(
            fn () => $workflow->approve($submitted, $otherDosen->id),
            'dosen',
            'Hanya dosen pembimbing akademik mahasiswa yang dapat memproses KRS ini.',
        );

        $approved = $workflow->approve($submitted, $context['dosen']->id);

        $this->assertSame('approved', $approved->status);
        $this->assertSame((int) $context['dosen']->id, (int) $approved->disetujui_oleh_dosen_id);
        $this->assertNotNull($approved->disetujui_pada);
    }

    public function test_component_scores_are_weighted_and_each_final_grade_change_is_audited(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $context['krs']->update(['status' => 'approved']);
        $detail = $context['krs']->details()->create([
            'mata_kuliah_id' => $class->mata_kuliah_id,
            'kelas_perkuliahan_id' => $class->id,
        ]);
        $components = collect([
            ['nama' => 'Tugas', 'bobot' => 30],
            ['nama' => 'UTS', 'bobot' => 30],
            ['nama' => 'UAS', 'bobot' => 40],
        ])->map(fn (array $data) => KomponenNilai::create([
            ...$data,
            'kelas_perkuliahan_id' => $class->id,
        ]));
        $service = new GradeService;

        $service->recordComponentScore($detail, $components[0]->id, $context['dosen']->id, 80, 'Input tugas');
        $service->recordComponentScore($detail, $components[1]->id, $context['dosen']->id, 70, 'Input UTS');
        $graded = $service->recordComponentScore($detail, $components[2]->id, $context['dosen']->id, 90, 'Input UAS');

        $this->assertSame('81.00', $graded->nilai_akhir_angka);
        $this->assertSame('A', $graded->nilai_huruf);

        $updated = $service->recordComponentScore($detail, $components[2]->id, $context['dosen']->id, 50, 'Koreksi nilai UAS');

        $this->assertSame('65.00', $updated->nilai_akhir_angka);
        $this->assertSame('C', $updated->nilai_huruf);
        $this->assertDatabaseCount('riwayat_perubahan_nilais', 2);
        $this->assertDatabaseHas('riwayat_perubahan_nilais', [
            'krs_detail_id' => $detail->id,
            'nilai_angka_lama' => 81,
            'nilai_huruf_lama' => 'A',
            'nilai_angka_baru' => 65,
            'nilai_huruf_baru' => 'C',
            'alasan' => 'Koreksi nilai UAS',
        ]);
    }

    public function test_schedule_rejects_an_invalid_weekday(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());

        $this->assertValidationError(
            fn () => (new ScheduleService)->schedule($class->id, $context['room']->id, 'funday', '09:00', '10:00'),
            'hari',
            'Hari jadwal perkuliahan tidak valid.',
        );
    }

    public function test_schedule_rejects_a_room_smaller_than_class_capacity(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail(), 41);

        $this->assertValidationError(
            fn () => (new ScheduleService)->schedule($class->id, $context['room']->id, 'senin', '09:00', '10:00'),
            'ruangan',
            'Kapasitas ruangan lebih kecil daripada kuota kelas.',
        );
    }

    public function test_krs_rejection_requires_a_reason(): void
    {
        $context = $this->academicContext();
        $context['krs']->update(['status' => 'submitted']);

        $this->assertValidationError(
            fn () => (new KrsWorkflowService)->reject($context['krs'], $context['dosen']->id, '  '),
            'reason',
            'Alasan penolakan wajib diisi.',
        );
    }

    public function test_grade_rejects_scores_outside_the_supported_range(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $component = KomponenNilai::create([
            'kelas_perkuliahan_id' => $class->id,
            'nama' => 'Ujian',
            'bobot' => 100,
        ]);
        $context['krs']->update(['status' => 'approved']);
        $detail = $context['krs']->details()->create([
            'mata_kuliah_id' => $class->mata_kuliah_id,
            'kelas_perkuliahan_id' => $class->id,
        ]);

        $this->assertValidationError(
            fn () => (new GradeService)->recordComponentScore($detail, $component->id, $context['dosen']->id, 100.01, 'Input ujian'),
            'nilai',
            'Nilai komponen harus berada pada rentang 0 sampai 100.',
        );

        $this->assertDatabaseCount('krs_detail_nilais', 0);
    }

    public function test_grade_rejects_an_unassigned_lecturer(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $class->komponenNilais()->create(['nama' => 'Ujian', 'bobot' => 100]);
        $context['krs']->update(['status' => 'approved']);
        $detail = $context['krs']->details()->create([
            'mata_kuliah_id' => $class->mata_kuliah_id,
            'kelas_perkuliahan_id' => $class->id,
        ]);
        $otherLecturer = Dosen::create([
            'program_studi_id' => $context['program']->id,
            'nid' => 'DTEST997',
            'nama' => 'Dosen Lain',
            'email' => 'dosen.lain@example.test',
        ]);

        $this->assertValidationError(
            fn () => (new GradeService)->recordComponentScore($detail, $class->komponenNilais()->first()->id, $otherLecturer->id, 80, 'Input nilai'),
            'dosen',
            'Hanya dosen pengampu yang dapat menginput nilai kelas.',
        );

        $this->assertDatabaseCount('krs_detail_nilais', 0);
    }

    public function test_attendance_rejects_an_unassigned_lecturer(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $context['krs']->update(['status' => 'approved']);
        $detail = $context['krs']->details()->create([
            'mata_kuliah_id' => $class->mata_kuliah_id,
            'kelas_perkuliahan_id' => $class->id,
        ]);
        $attendance = new AttendanceService;
        $meeting = $attendance->createMeeting(
            $class->id,
            $context['dosen']->id,
            1,
            $context['period']->tanggal_mulai->toDateString(),
        );
        $otherLecturer = Dosen::create([
            'program_studi_id' => $context['program']->id,
            'nid' => 'DTEST996',
            'nama' => 'Dosen Lain',
            'email' => 'dosen.lain@example.test',
        ]);

        $this->assertValidationError(
            fn () => $attendance->record($meeting, $detail->id, $otherLecturer->id, 'hadir'),
            'dosen',
            'Hanya dosen pengampu kelas yang dapat mencatat presensi.',
        );

        $this->assertDatabaseCount('presensis', 0);
    }

    public function test_krs_rejects_classes_when_seats_are_full_or_student_schedule_overlaps(): void
    {
        $context = $this->academicContext();
        $firstClass = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail(), 1);
        $secondClass = $this->createClass($context, MataKuliah::where('kode', 'IF102')->firstOrFail(), 1);
        $firstClass->jadwals()->create([
            'ruangan_id' => $context['room']->id,
            'hari' => 'senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:00',
        ]);
        $secondRoom = Ruangan::create([
            'kode' => 'TEST-R2',
            'nama' => 'Ruang Tes 2',
            'kapasitas' => 40,
        ]);
        $otherDosen = Dosen::create([
            'program_studi_id' => $context['program']->id,
            'nid' => 'DTEST998',
            'nama' => 'Dosen Kedua',
            'email' => 'dosen.kedua@example.test',
        ]);
        $secondClass->update(['dosen_id' => $otherDosen->id]);
        $secondClass->jadwals()->create([
            'ruangan_id' => $secondRoom->id,
            'hari' => 'senin',
            'jam_mulai' => '09:30',
            'jam_selesai' => '10:30',
        ]);

        $workflow = new KrsWorkflowService;
        $workflow->addClass($context['krs'], $firstClass->id);
        $this->assertValidationError(
            fn () => $workflow->addClass($context['krs'], $secondClass->id),
            'jadwal',
            'Jadwal kelas bertabrakan dengan kelas lain di KRS mahasiswa.',
        );

        $otherContext = $this->createAdditionalStudentContext($context);
        $this->assertValidationError(
            fn () => $workflow->addClass($otherContext['krs'], $firstClass->id),
            'kelas',
            'Kuota kelas telah terpenuhi.',
        );
    }

    public function test_ips_uses_grade_points_weighted_by_course_credits(): void
    {
        $context = $this->academicContext();
        $context['krs']->update(['status' => 'approved']);
        $threeCreditCourse = MataKuliah::where('kode', 'IF101')->firstOrFail();
        $twoCreditCourse = MataKuliah::where('kode', 'IF103')->firstOrFail();
        $context['krs']->details()->create([
            'mata_kuliah_id' => $threeCreditCourse->id,
            'nilai_huruf' => 'A',
        ]);
        $context['krs']->details()->create([
            'mata_kuliah_id' => $twoCreditCourse->id,
            'nilai_huruf' => 'C',
        ]);

        $ips = (new GradeService)->calculateIps($context['student']->id, $context['period']->id);

        $this->assertSame(3.2, $ips);
    }

    public function test_uas_eligibility_requires_at_least_seventy_five_percent_attendance(): void
    {
        $context = $this->academicContext();
        $class = $this->createClass($context, MataKuliah::where('kode', 'IF101')->firstOrFail());
        $context['krs']->update(['status' => 'approved']);
        $detail = $context['krs']->details()->create([
            'mata_kuliah_id' => $class->mata_kuliah_id,
            'kelas_perkuliahan_id' => $class->id,
        ]);
        $attendance = new AttendanceService;

        for ($number = 1; $number <= 16; $number++) {
            $meeting = $attendance->createMeeting(
                $class->id,
                $context['dosen']->id,
                $number,
                $context['period']->tanggal_mulai->copy()->addDays($number)->toDateString(),
            );
            $attendance->record(
                $meeting,
                $detail->id,
                $context['dosen']->id,
                $number <= 12 ? 'hadir' : 'alpa',
            );
        }

        $this->assertTrue($attendance->isEligibleForUas($detail));

        $firstMeeting = $class->pertemuanKuliahs()->where('nomor_pertemuan', 1)->firstOrFail();
        $attendance->record($firstMeeting, $detail->id, $context['dosen']->id, 'alpa');

        $this->assertFalse($attendance->isEligibleForUas($detail));
    }

    private function academicContext(): array
    {
        $this->seed(KampusSeeder::class);

        $program = ProgramStudi::where('kode', 'FIK-IF')->firstOrFail();
        $faculty = Fakultas::findOrFail($program->fakultas_id);
        $dosen = Dosen::where('program_studi_id', $program->id)->firstOrFail();
        $student = Mahasiswa::create([
            'fakultas_id' => $faculty->id,
            'program_studi_id' => $program->id,
            'dosen_pa_id' => $dosen->id,
            'nim' => 'TEST'.str_pad((string) ++$this->studentSequence, 10, '0', STR_PAD_LEFT),
            'nama' => 'Mahasiswa Tes',
            'email' => 'mahasiswa.tes'.$this->studentSequence.'@example.test',
            'angkatan' => 2026,
        ]);
        $period = SemesterAkademik::create([
            'kode' => '2026/2027-2',
            'tahun_akademik' => '2026/2027',
            'semester' => 'genap',
            'tanggal_mulai' => now()->addMonths(3)->toDateString(),
            'tanggal_selesai' => now()->addMonths(9)->toDateString(),
            'krs_dibuka_pada' => now()->subDay(),
            'krs_ditutup_pada' => now()->addDays(30),
            'jumlah_pertemuan_standar' => 16,
            'persentase_minimum_kehadiran_uas' => 75,
        ]);
        $krs = Krs::create([
            'mahasiswa_id' => $student->id,
            'semester_akademik_id' => $period->id,
            'tahun_akademik' => '2026/2027',
            'semester' => 'Genap',
            'status' => 'draft',
        ]);
        $room = Ruangan::create([
            'kode' => 'TEST-R1',
            'nama' => 'Ruang Tes 1',
            'kapasitas' => 40,
        ]);

        return compact('program', 'faculty', 'dosen', 'student', 'period', 'krs', 'room');
    }

    private function createClass(array $context, MataKuliah $course, ?int $quota = null): KelasPerkuliahan
    {
        static $section = 0;
        $section++;

        return KelasPerkuliahan::create([
            'mata_kuliah_id' => $course->id,
            'dosen_id' => $context['dosen']->id,
            'semester_akademik_id' => $context['period']->id,
            'kode_kelas' => 'T'.str_pad((string) $section, 2, '0', STR_PAD_LEFT),
            'kuota' => $quota ?? 40,
            'status' => 'open',
        ]);
    }

    private function createAdditionalStudentContext(array $context): array
    {
        $student = Mahasiswa::create([
            'fakultas_id' => $context['faculty']->id,
            'program_studi_id' => $context['program']->id,
            'dosen_pa_id' => $context['dosen']->id,
            'nim' => 'TEST'.str_pad((string) ++$this->studentSequence, 10, '0', STR_PAD_LEFT),
            'nama' => 'Mahasiswa Tes Tambahan',
            'email' => 'mahasiswa.tes'.$this->studentSequence.'@example.test',
            'angkatan' => 2026,
        ]);
        $krs = Krs::create([
            'mahasiswa_id' => $student->id,
            'semester_akademik_id' => $context['period']->id,
            'tahun_akademik' => '2026/2027',
            'semester' => 'Genap',
            'status' => 'draft',
        ]);

        return [...$context, 'student' => $student, 'krs' => $krs];
    }

    private function assertValidationError(callable $action, string $field, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()[$field] ?? []);
        }
    }
}
