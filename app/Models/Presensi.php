<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    protected $fillable = ['pertemuan_kuliah_id', 'krs_detail_id', 'status', 'dicatat_pada'];

    protected function casts(): array
    {
        return ['dicatat_pada' => 'datetime'];
    }

    public function pertemuanKuliah(): BelongsTo
    {
        return $this->belongsTo(PertemuanKuliah::class, 'pertemuan_kuliah_id');
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class);
    }
}
