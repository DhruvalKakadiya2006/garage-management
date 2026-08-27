<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabourMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_name', 'category', 'standard_rate',
        'taxable', 'gst_rate', 'hsn_sac_code', 'external_ref',
    ];

    protected $casts = [
        'standard_rate' => 'decimal:2',
        'taxable' => 'boolean',
        'gst_rate' => 'decimal:2',
    ];
}
