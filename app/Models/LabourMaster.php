<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabourMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_name', 'category', 'standard_rate', 'estimated_time_minutes',
        'taxable', 'gst_rate', 'hsn_sac_code',
    ];

    protected $casts = [
        'standard_rate' => 'decimal:2',
        'estimated_time_minutes' => 'integer',
        'taxable' => 'boolean',
        'gst_rate' => 'decimal:2',
    ];
}
