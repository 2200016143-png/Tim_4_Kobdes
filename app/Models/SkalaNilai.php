<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkalaNilai extends Model
{
    protected $table = 'skala_nilais';

    protected $fillable = ['nilai_huruf', 'nilai_minimum', 'nilai_maksimum', 'bobot_mutu'];

    protected function casts(): array
    {
        return [
            'nilai_minimum' => 'decimal:2',
            'nilai_maksimum' => 'decimal:2',
            'bobot_mutu' => 'decimal:2',
        ];
    }

    public function prasyaratMataKuliahs(): HasMany
    {
        return $this->hasMany(PrasyaratMataKuliah::class, 'nilai_minimum', 'nilai_huruf');
    }
}
