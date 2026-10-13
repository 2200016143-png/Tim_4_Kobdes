<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Krs extends Model
{
    protected $table = 'krs';

    protected $fillable = [
        'mahasiswa_id', 'semester_akademik_id', 'disetujui_oleh_dosen_id',
        'tahun_akademik', 'semester', 'status', 'diajukan_pada',
        'disetujui_pada', 'catatan_penolakan',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }

    public function semesterAkademik(): BelongsTo
    {
        return $this->belongsTo(SemesterAkademik::class);
    }

    public function disetujuiOlehDosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'disetujui_oleh_dosen_id');
    }
}
