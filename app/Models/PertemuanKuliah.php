<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PertemuanKuliah extends Model
{
    protected $table = 'pertemuan_kuliahs';

    protected $fillable = [
        'kelas_perkuliahan_id', 'nomor_pertemuan', 'tanggal', 'topik', 'status_presensi_dosen',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function kelasPerkuliahan(): BelongsTo
    {
        return $this->belongsTo(KelasPerkuliahan::class);
    }

    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class, 'pertemuan_kuliah_id');
    }
}
