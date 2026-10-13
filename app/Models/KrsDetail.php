<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KrsDetail extends Model
{
    protected $fillable = [
        'krs_id', 'mata_kuliah_id', 'kelas_perkuliahan_id', 'nilai_akhir_angka', 'nilai_huruf',
    ];

    protected function casts(): array
    {
        return ['nilai_akhir_angka' => 'decimal:2'];
    }

    public function krs(): BelongsTo
    {
        return $this->belongsTo(Krs::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function kelasPerkuliahan(): BelongsTo
    {
        return $this->belongsTo(KelasPerkuliahan::class);
    }

    public function nilaiKomponen(): HasMany
    {
        return $this->hasMany(KrsDetailNilai::class);
    }

    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function riwayatPerubahanNilais(): HasMany
    {
        return $this->hasMany(RiwayatPerubahanNilai::class);
    }
}
