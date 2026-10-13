<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasPerkuliahan extends Model
{
    protected $fillable = [
        'mata_kuliah_id', 'dosen_id', 'semester_akademik_id', 'kode_kelas', 'kuota', 'status',
    ];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function semesterAkademik(): BelongsTo
    {
        return $this->belongsTo(SemesterAkademik::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class);
    }

    public function krsDetails(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }

    public function komponenNilais(): HasMany
    {
        return $this->hasMany(KomponenNilai::class);
    }

    public function pertemuanKuliahs(): HasMany
    {
        return $this->hasMany(PertemuanKuliah::class);
    }
}
