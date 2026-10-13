<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrasyaratMataKuliah extends Model
{
    protected $fillable = ['mata_kuliah_id', 'prasyarat_id', 'nilai_minimum'];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function prasyarat(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'prasyarat_id');
    }
}
