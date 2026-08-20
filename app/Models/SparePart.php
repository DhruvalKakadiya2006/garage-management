<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_name', 'category', 'unit',
        'purchase_price', 'mrp', 'labor_charge', 'hsn_code', 'gst_rate',
        'current_stock_qty', 'reorder_level', 'external_ref',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'labor_charge' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'current_stock_qty' => 'integer',
        'reorder_level' => 'integer',
    ];

    protected function partCode(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('part_code') ?? $this->generatePartCode(),
        );
    }

    protected function generatePartCode(): string
    {
        $prefix = $this->category ? strtoupper(substr($this->category, 0, 3)) : 'PART';
        $lastPart = SparePart::max('part_code');

        if ($lastPart && preg_match('/^[A-Z]{2,3}-\d+$/', $lastPart)) {
            $lastNum = (int) explode('-', $lastPart)[1];
            $newNum = sprintf('%03d', $lastNum + 1);
            return "{$prefix}-{$newNum}";
        }

        return "{$prefix}-001";
    }

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
