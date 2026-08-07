<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_name', 'part_code', 'category', 'compatible_vehicle_brand', 'unit',
        'purchase_price', 'mrp', 'labor_charge', 'hsn_code', 'gst_rate',
        'current_stock_qty', 'reorder_level',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'labor_charge' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'current_stock_qty' => 'integer',
        'reorder_level' => 'integer',
    ];

    /**
     * Accessor for total_price (MRP + Labor Charge)
     */
    protected function totalPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mrp + $this->labor_charge,
        );
    }


}
