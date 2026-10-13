<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\JadwalKuliah;
use App\Models\KelasPerkuliahan;
use App\Models\Ruangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function schedule(
        int $kelasPerkuliahanId,
        int $ruanganId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
    ): JadwalKuliah {
        if (! in_array($hari, ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'], true)) {
            throw ValidationException::withMessages(['hari' => 'Hari jadwal perkuliahan tidak valid.']);
        }

        if ($jamMulai >= $jamSelesai) {
            throw ValidationException::withMessages(['jam_selesai' => 'Jam selesai harus setelah jam mulai.']);
        }

        return DB::transaction(function () use ($kelasPerkuliahanId, $ruanganId, $hari, $jamMulai, $jamSelesai): JadwalKuliah {
            $kelas = KelasPerkuliahan::query()
                ->lockForUpdate()
                ->with('semesterAkademik')
                ->findOrFail($kelasPerkuliahanId);
            Dosen::query()->lockForUpdate()->findOrFail($kelas->dosen_id);
            $ruangan = Ruangan::query()->lockForUpdate()->findOrFail($ruanganId);

            if ($kelas->kuota > $ruangan->kapasitas) {
                throw ValidationException::withMessages(['ruangan' => 'Kapasitas ruangan lebih kecil daripada kuota kelas.']);
            }

            $hasConflict = JadwalKuliah::query()
                ->where('hari', $hari)
                ->where('jam_mulai', '<', $jamSelesai)
                ->where('jam_selesai', '>', $jamMulai)
                ->whereHas('kelasPerkuliahan', function ($query) use ($kelas, $ruanganId): void {
                    $query->where('semester_akademik_id', $kelas->semester_akademik_id)
                        ->where(function ($query) use ($kelas, $ruanganId): void {
                            $query->where('dosen_id', $kelas->dosen_id)
                                ->orWhere('kelas_perkuliahans.id', $kelas->id)
                                ->orWhere('jadwal_kuliahs.ruangan_id', $ruanganId);
                        });
                })
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'jadwal' => 'Jadwal bertabrakan dengan jadwal dosen, kelas, atau ruangan pada semester yang sama.',
                ]);
            }

            return $kelas->jadwals()->create([
                'ruangan_id' => $ruangan->id,
                'hari' => $hari,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
            ]);
        });
    }
}
