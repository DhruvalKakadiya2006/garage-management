<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimateLabour extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id', 'labour_master_id', 'rate', 'qty', 'line_total',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'qty' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function labourMaster()
    {
        return $this->belongsTo(LabourMaster::class);
    }
}
