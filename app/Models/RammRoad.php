<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouncil;
use Illuminate\Database\Eloquent\Model;

class RammRoad extends Model
{
    use BelongsToCouncil;

    protected $primaryKey = 'road_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'road_id',
        'road_name',
        'total_rp',
        'line',
        'sections',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rp'    => 'float',
            'line'        => 'array',
            'sections'    => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function surfacings()
    {
        return $this->hasMany(RammSurfacing::class, 'road_id', 'road_id');
    }

    public function treatmentLengths()
    {
        return $this->hasMany(RammTreatmentLength::class, 'road_id', 'road_id');
    }

    public function fwp()
    {
        return $this->hasMany(RammFwp::class, 'road_id', 'road_id');
    }
}
