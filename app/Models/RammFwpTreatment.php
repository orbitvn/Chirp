<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RammFwpTreatment extends Model
{
    protected $table = 'ramm_fwp_treatments';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code', 'category', 'asset_type', 'ra1_rate', 'ra2_rate', 'active', 'seq',
    ];

    protected function casts(): array
    {
        return [
            'ra1_rate' => 'float',
            'ra2_rate' => 'float',
            'active'   => 'boolean',
            'seq'      => 'integer',
        ];
    }
}
