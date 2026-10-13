<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    protected $fillable = ['fakultas_id', 'program_studi_id', 'kode', 'nama', 'sks', 'semester'];

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function prasyarat(): HasMany
    {
        return $this->hasMany(PrasyaratMataKuliah::class);
    }

    public function kurikulums(): BelongsToMany
    {
        return $this->belongsToMany(Kurikulum::class, 'kurikulum_mata_kuliah')
            ->withPivot(['semester_rekomendasi', 'wajib'])
            ->withTimestamps();
    }

    public function kelasPerkuliahans(): HasMany
    {
        return $this->hasMany(KelasPerkuliahan::class);
    }

    public function krsDetails(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }
}
