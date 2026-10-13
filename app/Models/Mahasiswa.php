<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    protected $fillable = [
        'fakultas_id', 'program_studi_id', 'dosen_pa_id', 'nim', 'nama', 'email', 'angkatan',
    ];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function dosenPa(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pa_id');
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class);
    }
}
