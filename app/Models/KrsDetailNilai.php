<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsDetailNilai extends Model
{
    protected $table = 'krs_detail_nilais';

    protected $fillable = ['krs_detail_id', 'komponen_nilai_id', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2'];
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class);
    }

    public function komponenNilai(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class);
    }
}
