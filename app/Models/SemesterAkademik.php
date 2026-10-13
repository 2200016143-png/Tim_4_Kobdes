<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SemesterAkademik extends Model
{
    protected $fillable = [
        'kode', 'tahun_akademik', 'semester', 'tanggal_mulai', 'tanggal_selesai',
        'krs_dibuka_pada', 'krs_ditutup_pada', 'jumlah_pertemuan_standar',
        'persentase_minimum_kehadiran_uas',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'krs_dibuka_pada' => 'datetime',
            'krs_ditutup_pada' => 'datetime',
            'persentase_minimum_kehadiran_uas' => 'decimal:2',
        ];
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class);
    }

    public function kelasPerkuliahans(): HasMany
    {
        return $this->hasMany(KelasPerkuliahan::class);
    }
}
