<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimatePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id', 'spare_part_id', 'quantity', 'mrp', 'labor_charge', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'mrp' => 'decimal:2',
        'labor_charge' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }
}
