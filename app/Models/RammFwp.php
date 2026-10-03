<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouncil;
use Illuminate\Database\Eloquent\Model;

class RammFwp extends Model
{
    use BelongsToCouncil;

    protected $table = 'ramm_fwp';

    protected $fillable = [
        'road_id', 'treat_length_id', 'works_id', 'start_m', 'end_m',
        'treatment_id', 'treatment', 'category', 'fw_year', 'year_start',
        'reason', 'rank_score', 'cost', 'coverage_pct', 'locked',
    ];

    protected function casts(): array
    {
        return [
            'start_m'      => 'float',
            'end_m'        => 'float',
            'rank_score'   => 'float',
            'cost'         => 'float',
            'coverage_pct' => 'float',
            'year_start'   => 'integer',
            'locked'       => 'boolean',
        ];
    }
}
