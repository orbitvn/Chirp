<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Point extends Model
{
    protected $fillable = [
        'name',
        'description',
        'value',
        'color',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'lat'   => 'float',
            'lng'   => 'float',
        ];
    }
}
