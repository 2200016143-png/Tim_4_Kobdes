<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kurikulum extends Model
{
    protected $fillable = ['program_studi_id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai'];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function mataKuliahs(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'kurikulum_mata_kuliah')
            ->withPivot(['semester_rekomendasi', 'wajib'])
            ->withTimestamps();
    }
}
