<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RammSurfacing extends Model
{
    protected $fillable = [
        'road_id',
        'start_m',
        'end_m',
        'surf_offset',
        'surface_date',
        'surf_material',
        'surf_function',
        'chip_size',
        'chip_2nd_size',
        'surf_width',
        'life',
    ];

    protected function casts(): array
    {
        return [
            'start_m'      => 'float',
            'end_m'        => 'float',
            'surf_offset'  => 'float',
            'surface_date' => 'date',
            'surf_width'   => 'float',
            'life'         => 'integer',
        ];
    }
}
