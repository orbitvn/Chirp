<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $fillable = [
        'name',
        'description',
        'value',
        'color',
        'coordinates',
    ];

    protected function casts(): array
    {
        return [
            'value'       => 'float',
            'coordinates' => 'array',
        ];
    }
}
