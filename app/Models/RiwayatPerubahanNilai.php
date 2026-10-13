<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPerubahanNilai extends Model
{
    protected $table = 'riwayat_perubahan_nilais';

    protected $fillable = [
        'krs_detail_id', 'dosen_id', 'nilai_angka_lama', 'nilai_huruf_lama',
        'nilai_angka_baru', 'nilai_huruf_baru', 'alasan', 'diubah_pada',
    ];

    protected function casts(): array
    {
        return ['diubah_pada' => 'datetime'];
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
