<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouncil;
use Illuminate\Database\Eloquent\Model;

class FwpOverride extends Model
{
    use BelongsToCouncil;

    protected $table = 'fwp_overrides';

    protected $fillable = [
        'road_id', 'treat_length_id', 'start_m', 'end_m',
        'base_year', 'base_treatment',
        'year', 'year_start', 'treatment_id', 'treatment',
        'note', 'user', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'start_m'    => 'float',
            'end_m'      => 'float',
            'year_start' => 'integer',
            'synced_at'  => 'datetime',
        ];
    }
}
