<?php

namespace App\Services;

use App\Models\KomponenNilai;
use App\Models\KrsDetail;
use App\Models\KrsDetailNilai;
use App\Models\RiwayatPerubahanNilai;
use App\Models\SkalaNilai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeService
{
    public function recordComponentScore(
        KrsDetail $detail,
        int $komponenNilaiId,
        int $dosenId,
        float $nilai,
        string $alasan,
    ): KrsDetail {
        if ($nilai < 0 || $nilai > 100) {
            throw ValidationException::withMessages(['nilai' => 'Nilai komponen harus berada pada rentang 0 sampai 100.']);
        }

        if (trim($alasan) === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan input atau perubahan nilai wajib dicatat.']);
        }

        return DB::transaction(function () use ($detail, $komponenNilaiId, $dosenId, $nilai, $alasan): KrsDetail {
            $detail = KrsDetail::query()->lockForUpdate()->with('krs')->findOrFail($detail->id);
            $komponen = KomponenNilai::query()->findOrFail($komponenNilaiId);
            $kelas = $detail->kelasPerkuliahan()->with('dosen')->firstOrFail();

            if ((int) $kelas->dosen_id !== $dosenId) {
                throw ValidationException::withMessages(['dosen' => 'Hanya dosen pengampu yang dapat menginput nilai kelas.']);
            }

            if ((int) $komponen->kelas_perkuliahan_id !== (int) $kelas->id) {
                throw ValidationException::withMessages(['komponen' => 'Komponen nilai tidak termasuk kelas mahasiswa ini.']);
            }

            if ($detail->krs->status !== 'approved') {
                throw ValidationException::withMessages(['krs' => 'Nilai hanya dapat diinput untuk KRS yang telah disetujui.']);
            }

            $components = $kelas->komponenNilais()->get();
            if ($components->isEmpty() || abs((float) $components->sum('bobot') - 100) > 0.001) {
                throw ValidationException::withMessages(['bobot' => 'Total bobot komponen nilai kelas harus tepat 100%.']);
            }

            KrsDetailNilai::query()->updateOrCreate(
                [
                    'krs_detail_id' => $detail->id,
                    'komponen_nilai_id' => $komponen->id,
                ],
                ['nilai' => round($nilai, 2)],
            );

            $scores = $detail->nilaiKomponen()->with('komponenNilai')->get();
            if ($scores->count() < $components->count()) {
                return $detail->refresh();
            }

            $finalScore = round((float) $scores->sum(
                fn (KrsDetailNilai $score): float => (float) $score->nilai * (float) $score->komponenNilai->bobot / 100,
            ), 2);
            $scale = SkalaNilai::query()
                ->where('nilai_minimum', '<=', $finalScore)
                ->where('nilai_maksimum', '>=', $finalScore)
                ->first();

            if ($scale === null) {
                throw ValidationException::withMessages(['nilai' => 'Skala nilai belum mencakup nilai akhir hasil pembobotan.']);
            }

            if ((float) $detail->nilai_akhir_angka !== $finalScore || $detail->nilai_huruf !== $scale->nilai_huruf) {
                RiwayatPerubahanNilai::query()->create([
                    'krs_detail_id' => $detail->id,
                    'dosen_id' => $dosenId,
                    'nilai_angka_lama' => $detail->nilai_akhir_angka,
                    'nilai_huruf_lama' => $detail->nilai_huruf,
                    'nilai_angka_baru' => $finalScore,
                    'nilai_huruf_baru' => $scale->nilai_huruf,
                    'alasan' => trim($alasan),
                    'diubah_pada' => now(),
                ]);
            }

            $detail->forceFill([
                'nilai_akhir_angka' => $finalScore,
                'nilai_huruf' => $scale->nilai_huruf,
            ])->save();

            return $detail->refresh();
        });
    }

    public function calculateIps(int $mahasiswaId, int $semesterAkademikId): ?float
    {
        $details = KrsDetail::query()
            ->with(['mataKuliah', 'krs'])
            ->whereHas('krs', fn ($query) => $query
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('semester_akademik_id', $semesterAkademikId)
                ->where('status', 'approved'))
            ->whereNotNull('nilai_huruf')
            ->get();

        if ($details->isEmpty()) {
            return null;
        }

        $gradePoints = SkalaNilai::query()->pluck('bobot_mutu', 'nilai_huruf');
        $credits = 0;
        $points = 0.0;

        foreach ($details as $detail) {
            $gradePoint = $gradePoints->get($detail->nilai_huruf);
            if ($gradePoint === null) {
                continue;
            }

            $courseCredits = (int) $detail->mataKuliah->sks;
            $credits += $courseCredits;
            $points += (float) $gradePoint * $courseCredits;
        }

        return $credits === 0 ? null : round($points / $credits, 2);
    }
}
