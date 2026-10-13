<?php

namespace App\Services;

use App\Models\JadwalKuliah;
use App\Models\KebijakanBebanSks;
use App\Models\KelasPerkuliahan;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\MataKuliah;
use App\Models\PrasyaratMataKuliah;
use App\Models\SkalaNilai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KrsWorkflowService
{
    public function addClass(Krs $krs, int $kelasPerkuliahanId): KrsDetail
    {
        return DB::transaction(function () use ($krs, $kelasPerkuliahanId): KrsDetail {
            $krs = Krs::query()->lockForUpdate()->findOrFail($krs->id);
            $kelas = KelasPerkuliahan::query()->lockForUpdate()->findOrFail($kelasPerkuliahanId);
            $krs->loadMissing(['mahasiswa', 'semesterAkademik']);
            $kelas->loadMissing(['mataKuliah', 'semesterAkademik', 'jadwals']);

            $this->assertEditable($krs);
            $this->assertKrsWindowOpen($krs);
            $this->assertClassAvailable($krs, $kelas);
            $this->assertSameProgram($krs, $kelas->mataKuliah);
            $this->assertPrerequisitesMet($krs, $kelas->mataKuliah);
            $this->assertNoStudentScheduleConflict($krs, $kelas);
            $this->assertKrsCreditLimit($krs, $kelas->mataKuliah);
            $this->assertClassHasCapacity($kelas);

            return $krs->details()->create([
                'mata_kuliah_id' => $kelas->mata_kuliah_id,
                'kelas_perkuliahan_id' => $kelas->id,
            ]);
        });
    }

    public function submit(Krs $krs): Krs
    {
        return DB::transaction(function () use ($krs): Krs {
            $krs = Krs::query()->lockForUpdate()->with(['mahasiswa', 'semesterAkademik'])->findOrFail($krs->id);
            $this->assertEditable($krs);
            $this->assertKrsWindowOpen($krs);

            if ($krs->details()->count() === 0) {
                throw ValidationException::withMessages(['krs' => 'KRS harus berisi minimal satu kelas sebelum diajukan.']);
            }

            $kelasIds = $krs->details()->pluck('kelas_perkuliahan_id');
            foreach ($kelasIds as $kelasId) {
                $kelas = KelasPerkuliahan::query()
                    ->with(['mataKuliah', 'semesterAkademik', 'jadwals'])
                    ->findOrFail($kelasId);

                $this->assertClassAvailable($krs, $kelas);
                $this->assertSameProgram($krs, $kelas->mataKuliah);
                $this->assertPrerequisitesMet($krs, $kelas->mataKuliah);
                $this->assertNoStudentScheduleConflict($krs, $kelas, $kelasId);
            }

            $this->assertKrsCreditLimit($krs);

            $krs->forceFill([
                'status' => 'submitted',
                'diajukan_pada' => now(),
                'catatan_penolakan' => null,
            ])->save();

            return $krs->refresh();
        });
    }

    public function approve(Krs $krs, int $dosenId): Krs
    {
        return $this->resolve($krs, $dosenId, 'approved', null);
    }

    public function reject(Krs $krs, int $dosenId, string $reason): Krs
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->resolve($krs, $dosenId, 'rejected', trim($reason));
    }

    private function resolve(Krs $krs, int $dosenId, string $status, ?string $reason): Krs
    {
        return DB::transaction(function () use ($krs, $dosenId, $status, $reason): Krs {
            $krs = Krs::query()->lockForUpdate()->with('mahasiswa')->findOrFail($krs->id);

            if ($krs->status !== 'submitted') {
                throw ValidationException::withMessages(['krs' => 'Hanya KRS yang sudah diajukan yang dapat diproses DPA.']);
            }

            if ((int) $krs->mahasiswa->dosen_pa_id !== $dosenId) {
                throw ValidationException::withMessages(['dosen' => 'Hanya dosen pembimbing akademik mahasiswa yang dapat memproses KRS ini.']);
            }

            $krs->forceFill([
                'status' => $status,
                'disetujui_oleh_dosen_id' => $dosenId,
                'disetujui_pada' => now(),
                'catatan_penolakan' => $reason,
            ])->save();

            return $krs->refresh();
        });
    }

    private function assertEditable(Krs $krs): void
    {
        if ($krs->status !== 'draft') {
            throw ValidationException::withMessages(['krs' => 'KRS hanya dapat diubah saat berstatus draft.']);
        }
    }

    private function assertKrsWindowOpen(Krs $krs): void
    {
        $period = $krs->semesterAkademik;

        if ($period === null) {
            throw ValidationException::withMessages(['semester' => 'Semester akademik KRS belum ditetapkan.']);
        }

        $now = now();
        if ($period->krs_dibuka_pada === null || $period->krs_ditutup_pada === null
            || $now->lt($period->krs_dibuka_pada) || $now->gt($period->krs_ditutup_pada)) {
            throw ValidationException::withMessages(['semester' => 'Periode pengisian KRS sedang tidak dibuka.']);
        }
    }

    private function assertClassAvailable(Krs $krs, KelasPerkuliahan $kelas): void
    {
        if ($kelas->status !== 'open') {
            throw ValidationException::withMessages(['kelas' => 'Kelas tidak sedang dibuka untuk pendaftaran.']);
        }

        if ((int) $kelas->semester_akademik_id !== (int) $krs->semester_akademik_id) {
            throw ValidationException::withMessages(['kelas' => 'Kelas harus berasal dari semester akademik KRS.']);
        }
    }

    private function assertSameProgram(Krs $krs, MataKuliah $mataKuliah): void
    {
        if ($krs->mahasiswa->program_studi_id === null || $mataKuliah->program_studi_id === null
            || (int) $krs->mahasiswa->program_studi_id !== (int) $mataKuliah->program_studi_id) {
            throw ValidationException::withMessages(['kelas' => 'Mahasiswa hanya dapat mengambil kelas dari program studinya.']);
        }
    }

    private function assertPrerequisitesMet(Krs $krs, MataKuliah $mataKuliah): void
    {
        $prerequisites = PrasyaratMataKuliah::query()
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->get();

        if ($prerequisites->isEmpty()) {
            return;
        }

        $gradePoints = SkalaNilai::query()->pluck('bobot_mutu', 'nilai_huruf');

        foreach ($prerequisites as $prerequisite) {
            $minimumPoint = $gradePoints->get($prerequisite->nilai_minimum);
            $hasPassed = KrsDetail::query()
                ->where('mata_kuliah_id', $prerequisite->prasyarat_id)
                ->whereNotNull('nilai_huruf')
                ->whereHas('krs', function ($query) use ($krs): void {
                    $query->where('mahasiswa_id', $krs->mahasiswa_id)
                        ->where('status', 'approved')
                        ->whereHas('semesterAkademik', fn ($semester) => $semester
                            ->where('tanggal_selesai', '<', $krs->semesterAkademik->tanggal_mulai));
                })
                ->get(['nilai_huruf'])
                ->contains(fn (KrsDetail $detail): bool => $gradePoints->has($detail->nilai_huruf)
                    && $minimumPoint !== null
                    && (float) $gradePoints->get($detail->nilai_huruf) >= (float) $minimumPoint);

            if (! $hasPassed) {
                $course = MataKuliah::find($prerequisite->prasyarat_id);
                throw ValidationException::withMessages([
                    'prasyarat' => "Prasyarat {$course?->kode} harus lulus minimal dengan nilai {$prerequisite->nilai_minimum}.",
                ]);
            }
        }
    }

    private function assertNoStudentScheduleConflict(Krs $krs, KelasPerkuliahan $kelas, ?int $ignoreDetailId = null): void
    {
        $candidateSchedules = $kelas->jadwals;
        if ($candidateSchedules->isEmpty()) {
            throw ValidationException::withMessages(['jadwal' => 'Kelas belum memiliki jadwal perkuliahan.']);
        }

        $existingSchedules = KrsDetail::query()
            ->where('kelas_perkuliahan_id', '!=', $kelas->id)
            ->when($ignoreDetailId !== null, fn ($query) => $query->where('id', '!=', $ignoreDetailId))
            ->whereHas('krs', fn ($query) => $query
                ->where('mahasiswa_id', $krs->mahasiswa_id)
                ->where('semester_akademik_id', $krs->semester_akademik_id)
                ->whereIn('status', ['draft', 'submitted', 'approved']))
            ->with('kelasPerkuliahan.jadwals')
            ->get()
            ->flatMap(fn (KrsDetail $detail) => $detail->kelasPerkuliahan?->jadwals ?? collect());

        foreach ($candidateSchedules as $candidate) {
            $conflict = $existingSchedules->contains(fn (JadwalKuliah $existing): bool => $existing->hari === $candidate->hari
                && $existing->jam_mulai < $candidate->jam_selesai
                && $existing->jam_selesai > $candidate->jam_mulai);

            if ($conflict) {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal kelas bertabrakan dengan kelas lain di KRS mahasiswa.']);
            }
        }
    }

    private function assertKrsCreditLimit(Krs $krs, ?MataKuliah $additionalCourse = null): void
    {
        $ips = $this->previousSemesterIps($krs);
        $policy = KebijakanBebanSks::query()
            ->where('ips_minimum', '<=', $ips)
            ->where('ips_maksimum', '>=', $ips)
            ->first();

        if ($policy === null) {
            throw ValidationException::withMessages(['krs' => 'Kebijakan batas SKS untuk IPS mahasiswa belum dikonfigurasi.']);
        }

        $selectedCredits = (int) $krs->details()
            ->with('mataKuliah:id,sks')
            ->get()
            ->sum(fn (KrsDetail $detail): int => (int) $detail->mataKuliah->sks);

        if ($additionalCourse !== null) {
            $selectedCredits += (int) $additionalCourse->sks;
        }

        if ($selectedCredits > $policy->sks_maksimum) {
            throw ValidationException::withMessages([
                'sks' => "Beban KRS {$selectedCredits} SKS melebihi batas {$policy->sks_maksimum} SKS untuk IPS {$ips}.",
            ]);
        }
    }

    private function previousSemesterIps(Krs $krs): float
    {
        $previousKrs = Krs::query()
            ->with(['semesterAkademik', 'details.mataKuliah'])
            ->where('mahasiswa_id', $krs->mahasiswa_id)
            ->where('status', 'approved')
            ->whereHas('semesterAkademik', fn ($query) => $query
                ->where('tanggal_selesai', '<', $krs->semesterAkademik->tanggal_mulai))
            ->get()
            ->sortByDesc(fn (Krs $record) => $record->semesterAkademik->tanggal_selesai)
            ->first();

        if ($previousKrs === null) {
            return 0.0;
        }

        $gradePoints = SkalaNilai::query()->pluck('bobot_mutu', 'nilai_huruf');
        $totalCredits = 0;
        $totalPoints = 0.0;

        foreach ($previousKrs->details as $detail) {
            $gradePoint = $gradePoints->get($detail->nilai_huruf);
            if ($gradePoint === null) {
                continue;
            }

            $credits = (int) $detail->mataKuliah->sks;
            $totalCredits += $credits;
            $totalPoints += (float) $gradePoint * $credits;
        }

        return $totalCredits === 0 ? 0.0 : round($totalPoints / $totalCredits, 2);
    }

    private function assertClassHasCapacity(KelasPerkuliahan $kelas): void
    {
        $occupiedSeats = $kelas->krsDetails()
            ->whereHas('krs', fn ($query) => $query->whereIn('status', ['draft', 'submitted', 'approved']))
            ->count();

        if ($occupiedSeats >= $kelas->kuota) {
            throw ValidationException::withMessages(['kelas' => 'Kuota kelas telah terpenuhi.']);
        }
    }
}
