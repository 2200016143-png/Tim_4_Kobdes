<?php

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LaporanDataSeeder extends Seeder
{
    private const STUDENT_COUNT = 2_000;

    private const BATCH_SIZE = 100;

    private const SECTIONS_PER_COURSE = 18;

    private const STUDENTS_PER_SECTION = 38;

    private const ACADEMIC_YEAR = '2026/2027';

    private const SEMESTER = 'Ganjil';

    public function run(): void
    {
        $this->call(KampusSeeder::class);

        $semester = DB::table('semester_akademiks')
            ->where('kode', '2026/2027-1')
            ->first();

        if ($semester === null) {
            throw new RuntimeException('Semester akademik 2026/2027 ganjil tidak ditemukan.');
        }

        DB::transaction(function () use ($semester): void {
            $programs = DB::table('program_studis')
                ->join('fakultas', 'fakultas.id', '=', 'program_studis.fakultas_id')
                ->select(
                    'program_studis.id',
                    'program_studis.kode',
                    'program_studis.fakultas_id',
                )
                ->orderBy('program_studis.id')
                ->get();

            $programCount = $programs->count();
            if ($programCount === 0) {
                throw new RuntimeException('Program studi belum tersedia untuk data dummy.');
            }

            $programIds = $programs->pluck('id')->all();
            $lecturerIds = DB::table('dosens')->pluck('id', 'program_studi_id')->all();
            $coursesByProgram = [];

            foreach (DB::table('mata_kuliahs')
                ->whereIn('program_studi_id', $programIds)
                ->orderBy('id')
                ->get() as $course) {
                $coursesByProgram[$course->program_studi_id][] = (int) $course->id;
            }

            $classRows = [];
            $timestamps = now()->toDateTimeString();
            foreach ($programs as $program) {
                $courseIds = array_slice($coursesByProgram[$program->id] ?? [], 0, 3);
                if (! isset($lecturerIds[$program->id]) || count($courseIds) !== 3) {
                    throw new RuntimeException("Dosen atau tiga mata kuliah untuk program studi {$program->kode} belum lengkap.");
                }

                foreach ($courseIds as $courseId) {
                    for ($section = 0; $section < self::SECTIONS_PER_COURSE; $section++) {
                        $classRows[] = [
                            'mata_kuliah_id' => $courseId,
                            'dosen_id' => $lecturerIds[$program->id],
                            'semester_akademik_id' => $semester->id,
                            'kode_kelas' => chr(ord('A') + $section),
                            'kuota' => 40,
                            'status' => 'open',
                            'created_at' => $timestamps,
                            'updated_at' => $timestamps,
                        ];
                    }
                }
            }

            $this->upsert('kelas_perkuliahans', $classRows, [
                'semester_akademik_id', 'mata_kuliah_id', 'kode_kelas',
            ], [
                'dosen_id', 'kuota', 'status', 'updated_at',
            ]);

            $classesByProgram = [];
            $classIds = [];
            foreach (DB::table('kelas_perkuliahans')
                ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_perkuliahans.mata_kuliah_id')
                ->where('kelas_perkuliahans.semester_akademik_id', $semester->id)
                ->orderBy('mata_kuliahs.program_studi_id')
                ->orderBy('mata_kuliahs.id')
                ->orderBy('kelas_perkuliahans.kode_kelas')
                ->select(
                    'kelas_perkuliahans.id as class_id',
                    'kelas_perkuliahans.mata_kuliah_id',
                    'kelas_perkuliahans.kode_kelas',
                    'mata_kuliahs.program_studi_id',
                )
                ->get() as $class) {
                $classesByProgram[$class->program_studi_id][$class->mata_kuliah_id][] = $class;
                $classIds[] = (int) $class->class_id;
            }

            $generatedClassIds = [];
            foreach ($classesByProgram as $courseClasses) {
                foreach ($courseClasses as $sections) {
                    foreach ($sections as $class) {
                        if ($class->kode_kelas !== 'A') {
                            $generatedClassIds[] = (int) $class->class_id;
                        }
                    }
                }
            }

            DB::table('jadwal_kuliahs')
                ->whereIn('kelas_perkuliahan_id', $generatedClassIds)
                ->delete();

            $baseSchedules = [];
            $roomByProgram = [];
            $scheduledClassIds = [];
            foreach (DB::table('jadwal_kuliahs')
                ->join('kelas_perkuliahans', 'kelas_perkuliahans.id', '=', 'jadwal_kuliahs.kelas_perkuliahan_id')
                ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_perkuliahans.mata_kuliah_id')
                ->where('kelas_perkuliahans.semester_akademik_id', $semester->id)
                ->select(
                    'jadwal_kuliahs.kelas_perkuliahan_id',
                    'jadwal_kuliahs.ruangan_id',
                    'jadwal_kuliahs.hari',
                    'jadwal_kuliahs.jam_mulai',
                    'jadwal_kuliahs.jam_selesai',
                    'mata_kuliahs.program_studi_id',
                )
                ->get() as $schedule) {
                $baseSchedules[$schedule->program_studi_id][] = $schedule;
                $scheduledClassIds[$schedule->kelas_perkuliahan_id] = true;
                $roomByProgram[$schedule->program_studi_id] ??= (int) $schedule->ruangan_id;
            }

            $rooms = DB::table('ruangans')->whereIn('id', array_values($roomByProgram))->pluck('kapasitas', 'id');
            foreach ($programs as $program) {
                $roomId = $roomByProgram[$program->id] ?? null;
                if ($roomId === null || (int) ($rooms[$roomId] ?? 0) < 40) {
                    throw new RuntimeException("Ruangan berkapasitas minimal 40 untuk program studi {$program->kode} tidak ditemukan.");
                }
            }

            $weekdays = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
            $startTimes = ['06:00', '07:45', '09:30', '11:15', '13:00', '14:45', '16:30', '18:15', '20:00', '21:45'];
            $scheduleSlots = [];
            foreach ($weekdays as $day) {
                foreach ($startTimes as $startTime) {
                    $endTime = (new DateTimeImmutable("2000-01-01 {$startTime}"))
                        ->modify('+90 minutes')
                        ->format('H:i:s');
                    $scheduleSlots[] = [
                        'hari' => $day,
                        'jam_mulai' => $startTime.':00',
                        'jam_selesai' => $endTime,
                    ];
                }
            }

            $scheduleRows = [];
            foreach ($programs as $program) {
                $occupiedSlots = [];
                foreach ($baseSchedules[$program->id] ?? [] as $schedule) {
                    $occupiedSlots[] = [
                        'hari' => $schedule->hari,
                        'jam_mulai' => $schedule->jam_mulai,
                        'jam_selesai' => $schedule->jam_selesai,
                    ];
                }

                $slotIndex = 0;
                foreach ($classesByProgram[$program->id] as $courseClasses) {
                    foreach ($courseClasses as $class) {
                        if (isset($scheduledClassIds[$class->class_id])) {
                            continue;
                        }

                        $slot = null;
                        while ($slotIndex < count($scheduleSlots)) {
                            $candidate = $scheduleSlots[$slotIndex++];
                            $hasConflict = false;
                            foreach ($occupiedSlots as $occupied) {
                                if ($occupied['hari'] === $candidate['hari']
                                    && $occupied['jam_mulai'] < $candidate['jam_selesai']
                                    && $occupied['jam_selesai'] > $candidate['jam_mulai']) {
                                    $hasConflict = true;
                                    break;
                                }
                            }

                            if (! $hasConflict) {
                                $slot = $candidate;
                                break;
                            }
                        }

                        if ($slot === null) {
                            throw new RuntimeException("Jadwal bebas untuk semua kelas program studi {$program->kode} tidak tersedia.");
                        }

                        $scheduleRows[] = [
                            'kelas_perkuliahan_id' => $class->class_id,
                            'ruangan_id' => $roomByProgram[$program->id],
                            ...$slot,
                            'created_at' => $timestamps,
                            'updated_at' => $timestamps,
                        ];
                        $occupiedSlots[] = $slot;
                    }
                }
            }

            $this->upsert('jadwal_kuliahs', $scheduleRows, [
                'kelas_perkuliahan_id', 'hari', 'jam_mulai',
            ], ['ruangan_id', 'jam_selesai', 'updated_at']);

            $componentRows = [];
            foreach ($classIds as $classId) {
                foreach ([['Tugas', 30], ['UTS', 30], ['UAS', 40]] as [$name, $weight]) {
                    $componentRows[] = [
                        'kelas_perkuliahan_id' => $classId,
                        'nama' => $name,
                        'bobot' => $weight,
                        'created_at' => $timestamps,
                        'updated_at' => $timestamps,
                    ];
                }
            }

            $this->upsert('komponen_nilais', $componentRows, [
                'kelas_perkuliahan_id', 'nama',
            ], ['bobot', 'updated_at']);

            $componentsByClass = [];
            foreach (DB::table('komponen_nilais')
                ->whereIn('kelas_perkuliahan_id', $classIds)
                ->get() as $component) {
                $componentsByClass[$component->kelas_perkuliahan_id][$component->nama] = (int) $component->id;
            }

            $componentNames = ['Tugas', 'UTS', 'UAS'];
            foreach ($classIds as $classId) {
                foreach ($componentNames as $name) {
                    if (! isset($componentsByClass[$classId][$name])) {
                        throw new RuntimeException("Komponen nilai {$name} untuk kelas {$classId} tidak ditemukan.");
                    }
                }
            }

            $meetingCount = (int) $semester->jumlah_pertemuan_standar;
            $firstMeeting = (new DateTimeImmutable($semester->tanggal_mulai))->modify('next monday');
            $meetingRows = [];
            $timestamps = now()->toDateTimeString();

            foreach ($classIds as $classId) {
                for ($number = 1; $number <= $meetingCount; $number++) {
                    $meetingDate = $firstMeeting->modify(sprintf('+%d weeks', $number - 1))->format('Y-m-d');

                    if ($meetingDate > $semester->tanggal_selesai) {
                        throw new RuntimeException('Jadwal 16 pertemuan melebihi tanggal akhir semester.');
                    }

                    $meetingRows[] = [
                        'kelas_perkuliahan_id' => $classId,
                        'nomor_pertemuan' => $number,
                        'tanggal' => $meetingDate,
                        'topik' => "Pertemuan {$number}",
                        'status_presensi_dosen' => 'hadir',
                        'created_at' => $timestamps,
                        'updated_at' => $timestamps,
                    ];
                }
            }

            $this->upsert(
                'pertemuan_kuliahs',
                $meetingRows,
                ['kelas_perkuliahan_id', 'nomor_pertemuan'],
                ['tanggal', 'topik', 'status_presensi_dosen', 'updated_at'],
            );

            $meetingsByClass = [];
            foreach (DB::table('pertemuan_kuliahs')
                ->whereIn('kelas_perkuliahan_id', $classIds)
                ->get() as $meeting) {
                $meetingsByClass[$meeting->kelas_perkuliahan_id][$meeting->nomor_pertemuan] = [
                    'id' => (int) $meeting->id,
                    'tanggal' => $meeting->tanggal,
                ];
            }

            $gradeValues = [
                'Tugas' => ['weight' => 0.30, 'offset' => 13],
                'UTS' => ['weight' => 0.30, 'offset' => 17],
                'UAS' => ['weight' => 0.40, 'offset' => 19],
            ];

            foreach ($programs as $programIndex => $program) {
                $programCourses = array_values($classesByProgram[$program->id]);
                for ($batchStart = 1; $batchStart <= self::STUDENT_COUNT; $batchStart += self::BATCH_SIZE) {
                    $studentRows = [];
                    $studentNumbers = [];
                    $timestamps = now()->toDateTimeString();

                    for ($number = $batchStart; $number < $batchStart + self::BATCH_SIZE && $number <= self::STUDENT_COUNT; $number++) {
                        if (($number - 1) % $programCount !== $programIndex) {
                            continue;
                        }

                        $admissionYear = 2019 + ($number % 8);
                        $nim = $admissionYear.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                        $studentRows[] = [
                            'fakultas_id' => $program->fakultas_id,
                            'program_studi_id' => $program->id,
                            'dosen_pa_id' => $lecturerIds[$program->id],
                            'nim' => $nim,
                            'nama' => $this->studentName($number),
                            'email' => "mahasiswa.{$nim}@example.test",
                            'alamat' => 'Jalan Contoh Nomor '.(($number % 200) + 1).', Kota Akademik',
                            'tanggal_lahir' => sprintf('%04d-%02d-%02d', 1998 + ($number % 8), ($number % 12) + 1, ($number % 28) + 1),
                            'angkatan' => $admissionYear,
                            'created_at' => $timestamps,
                            'updated_at' => $timestamps,
                        ];
                        $studentNumbers[$nim] = $number;
                    }

                    if ($studentRows === []) {
                        continue;
                    }

                    $this->upsert('mahasiswas', $studentRows, ['nim'], [
                        'fakultas_id', 'program_studi_id', 'dosen_pa_id', 'nama', 'email',
                        'alamat', 'tanggal_lahir', 'angkatan', 'updated_at',
                    ]);

                    $studentIds = DB::table('mahasiswas')
                        ->whereIn('nim', array_keys($studentNumbers))
                        ->pluck('id', 'nim')
                        ->all();

                    $krsRows = [];
                    foreach ($studentIds as $nim => $studentId) {
                        $krsRows[] = [
                            'mahasiswa_id' => $studentId,
                            'semester_akademik_id' => $semester->id,
                            'disetujui_oleh_dosen_id' => $lecturerIds[$program->id],
                            'tahun_akademik' => self::ACADEMIC_YEAR,
                            'semester' => self::SEMESTER,
                            'status' => 'approved',
                            'diajukan_pada' => '2026-07-10 09:00:00',
                            'disetujui_pada' => '2026-07-11 09:00:00',
                            'created_at' => $timestamps,
                            'updated_at' => $timestamps,
                        ];
                    }

                    $this->upsert('krs', $krsRows, [
                        'mahasiswa_id', 'tahun_akademik', 'semester',
                    ], [
                        'semester_akademik_id', 'disetujui_oleh_dosen_id', 'status',
                        'diajukan_pada', 'disetujui_pada', 'updated_at',
                    ]);

                    $krsIds = DB::table('krs')
                        ->whereIn('mahasiswa_id', array_values($studentIds))
                        ->where('tahun_akademik', self::ACADEMIC_YEAR)
                        ->where('semester', self::SEMESTER)
                        ->pluck('id', 'mahasiswa_id')
                        ->all();

                    $detailRows = [];
                    $detailMetadata = [];
                    foreach ($studentIds as $nim => $studentId) {
                        $studentNumber = $studentNumbers[$nim];
                        $krsId = $krsIds[$studentId];

                        $sectionIndex = intdiv(intdiv($studentNumber - 1, $programCount), self::STUDENTS_PER_SECTION);
                        foreach ($programCourses as $courseIndex => $sections) {
                            $class = $sections[$sectionIndex];
                            $scoreByComponent = [];
                            foreach ($gradeValues as $name => $grade) {
                                $scoreByComponent[$name] = 60 + (($studentNumber * $grade['offset'] + $courseIndex * 7) % 41);
                            }

                            $finalScore = round(
                                $scoreByComponent['Tugas'] * $gradeValues['Tugas']['weight']
                                + $scoreByComponent['UTS'] * $gradeValues['UTS']['weight']
                                + $scoreByComponent['UAS'] * $gradeValues['UAS']['weight'],
                                2,
                            );
                            $detailRows[] = [
                                'krs_id' => $krsId,
                                'mata_kuliah_id' => $class->mata_kuliah_id,
                                'kelas_perkuliahan_id' => $class->class_id,
                                'nilai_akhir_angka' => $finalScore,
                                'nilai_huruf' => $this->letterGrade($finalScore),
                                'created_at' => $timestamps,
                                'updated_at' => $timestamps,
                            ];
                            $detailMetadata[] = [
                                'krs_id' => $krsId,
                                'mata_kuliah_id' => $class->mata_kuliah_id,
                                'class_id' => (int) $class->class_id,
                                'student_number' => $studentNumber,
                                'course_index' => $courseIndex,
                                'scores' => $scoreByComponent,
                            ];
                        }
                    }

                    $this->upsert('krs_details', $detailRows, [
                        'krs_id', 'mata_kuliah_id',
                    ], [
                        'kelas_perkuliahan_id', 'nilai_akhir_angka', 'nilai_huruf', 'updated_at',
                    ]);

                    $detailsByKey = [];
                    foreach (DB::table('krs_details')
                        ->whereIn('krs_id', array_values($krsIds))
                        ->whereIn('mata_kuliah_id', array_column($detailMetadata, 'mata_kuliah_id'))
                        ->get() as $detail) {
                        $detailsByKey[$detail->krs_id.':'.$detail->mata_kuliah_id] = (int) $detail->id;
                    }

                    $batchDetailIds = array_values($detailsByKey);
                    DB::table('krs_detail_nilais')->whereIn('krs_detail_id', $batchDetailIds)->delete();
                    DB::table('presensis')->whereIn('krs_detail_id', $batchDetailIds)->delete();

                    $gradeRows = [];
                    $attendanceRows = [];
                    foreach ($detailMetadata as $metadata) {
                        $detailId = $detailsByKey[$metadata['krs_id'].':'.$metadata['mata_kuliah_id']];

                        foreach ($metadata['scores'] as $name => $score) {
                            $gradeRows[] = [
                                'krs_detail_id' => $detailId,
                                'komponen_nilai_id' => $componentsByClass[$metadata['class_id']][$name],
                                'nilai' => $score,
                                'created_at' => $timestamps,
                                'updated_at' => $timestamps,
                            ];
                        }

                        for ($number = 1; $number <= $meetingCount; $number++) {
                            $attendanceOffset = ($number + $metadata['student_number'] + $metadata['course_index']) % $meetingCount;
                            $status = $attendanceOffset < 13
                                ? 'hadir'
                                : ['izin', 'sakit', 'alpa'][$attendanceOffset - 13];
                            $meeting = $meetingsByClass[$metadata['class_id']][$number];

                            $attendanceRows[] = [
                                'pertemuan_kuliah_id' => $meeting['id'],
                                'krs_detail_id' => $detailId,
                                'status' => $status,
                                'dicatat_pada' => $meeting['tanggal'].' 15:30:00',
                                'created_at' => $timestamps,
                                'updated_at' => $timestamps,
                            ];
                        }
                    }

                    $this->upsert('krs_detail_nilais', $gradeRows, [
                        'krs_detail_id', 'komponen_nilai_id',
                    ], ['nilai', 'updated_at']);
                    $this->upsert('presensis', $attendanceRows, [
                        'pertemuan_kuliah_id', 'krs_detail_id',
                    ], ['status', 'dicatat_pada', 'updated_at']);
                }
            }
        });
    }

    private function upsert(string $table, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }

    private function studentName(int $number): string
    {
        $firstNames = ['Ayu', 'Bagus', 'Citra', 'Dimas', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Intan', 'Joko', 'Kartika', 'Lukman'];
        $lastNames = ['Pratama', 'Lestari', 'Wijaya', 'Saputra', 'Permata', 'Nugraha', 'Kusuma', 'Ramadhan'];

        return $firstNames[($number - 1) % count($firstNames)].' '.$lastNames[(intdiv($number - 1, count($firstNames))) % count($lastNames)];
    }

    private function letterGrade(float $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'E',
        };
    }
}
