<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dosen extends Model
{
    protected $fillable = ['user_id', 'program_studi_id', 'nid', 'nama', 'email', 'no_hp'];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function mahasiswasBimbingan(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'dosen_pa_id');
    }

    public function kelasPerkuliahans(): HasMany
    {
        return $this->hasMany(KelasPerkuliahan::class);
    }
}
