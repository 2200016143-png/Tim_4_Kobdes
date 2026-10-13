<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KebijakanBebanSks extends Model
{
    protected $table = 'kebijakan_beban_sks';

    protected $fillable = ['ips_minimum', 'ips_maksimum', 'sks_maksimum'];

    protected function casts(): array
    {
        return [
            'ips_minimum' => 'decimal:2',
            'ips_maksimum' => 'decimal:2',
        ];
    }
}
