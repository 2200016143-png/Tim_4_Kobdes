<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenNilai extends Model
{
    protected $table = 'komponen_nilais';

    protected $fillable = ['kelas_perkuliahan_id', 'nama', 'bobot'];

    public function kelasPerkuliahan(): BelongsTo
    {
        return $this->belongsTo(KelasPerkuliahan::class);
    }

    public function nilaiMahasiswas(): HasMany
    {
        return $this->hasMany(KrsDetailNilai::class, 'komponen_nilai_id');
    }
}
