<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RammTreatmentLength extends Model
{
    protected $fillable = [
        'road_id',
        'tl_id',
        'tl_name',
        'start_m',
        'end_m',
    ];

    protected function casts(): array
    {
        return [
            'start_m' => 'float',
            'end_m'   => 'float',
        ];
    }
}
