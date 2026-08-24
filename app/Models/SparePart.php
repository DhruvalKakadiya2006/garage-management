<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_name', 'part_code', 'category', 'unit',
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

    protected static function booted(): void
    {
        static::creating(function (SparePart $part) {
            if (blank($part->attributes['part_code'] ?? null)) {
                $part->part_code = $part->generatePartCode();
            }
        });
    }

    protected function generatePartCode(): string
    {
        $prefix = $this->category ? strtoupper(substr($this->category, 0, 3)) : 'PART';

        $maxSuffix = (int) static::query()
            ->where('part_code', 'like', $prefix.'-%')
            ->selectRaw('MAX(CAST(substr(part_code, ?) AS INTEGER)) as max_suffix', [strlen($prefix) + 2])
            ->value('max_suffix');

        return sprintf('%s-%03d', $prefix, max($maxSuffix, 0) + 1);
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
