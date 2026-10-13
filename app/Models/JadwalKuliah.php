<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalKuliah extends Model
{
    protected $fillable = ['kelas_perkuliahan_id', 'ruangan_id', 'hari', 'jam_mulai', 'jam_selesai'];

    public function kelasPerkuliahan(): BelongsTo
    {
        return $this->belongsTo(KelasPerkuliahan::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }
}
