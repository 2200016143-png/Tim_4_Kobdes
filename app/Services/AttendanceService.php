<?php

namespace App\Services;

use App\Models\KelasPerkuliahan;
use App\Models\KrsDetail;
use App\Models\PertemuanKuliah;
use App\Models\Presensi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    private const STATUSES = ['hadir', 'izin', 'sakit', 'alpa'];

    public function createMeeting(
        int $kelasPerkuliahanId,
        int $dosenId,
        int $nomorPertemuan,
        string $tanggal,
        ?string $topik = null,
    ): PertemuanKuliah {
        return DB::transaction(function () use ($kelasPerkuliahanId, $dosenId, $nomorPertemuan, $tanggal, $topik): PertemuanKuliah {
            $kelas = KelasPerkuliahan::query()
                ->lockForUpdate()
                ->with('semesterAkademik')
                ->findOrFail($kelasPerkuliahanId);

            if ((int) $kelas->dosen_id !== $dosenId) {
                throw ValidationException::withMessages(['dosen' => 'Hanya dosen pengampu kelas yang dapat membuat jurnal pertemuan.']);
            }

            $period = $kelas->semesterAkademik;
            if ($nomorPertemuan < 1 || $nomorPertemuan > $period->jumlah_pertemuan_standar) {
                throw ValidationException::withMessages(['nomor_pertemuan' => 'Nomor pertemuan di luar jumlah pertemuan semester.']);
            }

            if ($tanggal < $period->tanggal_mulai->toDateString() || $tanggal > $period->tanggal_selesai->toDateString()) {
                throw ValidationException::withMessages(['tanggal' => 'Tanggal pertemuan harus berada dalam semester akademik.']);
            }

            return PertemuanKuliah::query()->firstOrCreate(
                [
                    'kelas_perkuliahan_id' => $kelas->id,
                    'nomor_pertemuan' => $nomorPertemuan,
                ],
                ['tanggal' => $tanggal, 'topik' => $topik],
            );
        });
    }

    public function record(
        PertemuanKuliah $pertemuan,
        int $krsDetailId,
        int $dosenId,
        string $status,
    ): Presensi {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Status presensi tidak valid.']);
        }

        return DB::transaction(function () use ($pertemuan, $krsDetailId, $dosenId, $status): Presensi {
            $pertemuan = PertemuanKuliah::query()->lockForUpdate()->with('kelasPerkuliahan')->findOrFail($pertemuan->id);
            $detail = KrsDetail::query()->with('krs')->findOrFail($krsDetailId);

            if ((int) $pertemuan->kelasPerkuliahan->dosen_id !== $dosenId) {
                throw ValidationException::withMessages(['dosen' => 'Hanya dosen pengampu kelas yang dapat mencatat presensi.']);
            }

            if ((int) $detail->kelas_perkuliahan_id !== (int) $pertemuan->kelas_perkuliahan_id
                || $detail->krs->status !== 'approved') {
                throw ValidationException::withMessages(['mahasiswa' => 'Presensi hanya dapat dicatat untuk mahasiswa terdaftar pada kelas ini melalui KRS yang disetujui.']);
            }

            return Presensi::query()->updateOrCreate(
                [
                    'pertemuan_kuliah_id' => $pertemuan->id,
                    'krs_detail_id' => $detail->id,
                ],
                ['status' => $status, 'dicatat_pada' => now()],
            );
        });
    }

    public function isEligibleForUas(KrsDetail $detail): bool
    {
        $detail->loadMissing(['krs.semesterAkademik']);

        $period = $detail->krs->semesterAkademik;
        if ($period === null || $period->jumlah_pertemuan_standar < 1) {
            return false;
        }

        $attended = $detail->presensis()->where('status', 'hadir')->count();

        return $attended * 100 >= $period->jumlah_pertemuan_standar * (float) $period->persentase_minimum_kehadiran_uas;
    }
}
