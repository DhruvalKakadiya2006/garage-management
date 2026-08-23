<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'counter_invoice_id', 'spare_part_id', 'quantity', 'mrp', 'discount_percent', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'mrp' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * Spare part resolved during the "creating" event, reused by "created" so
     * a single save performs one locked read instead of two identical finds.
     */
    private ?SparePart $resolvedSparePart = null;

    protected static function boot()
    {
        parent::boot();

        // Guard against overselling before the row is inserted. The row lock
        // serialises concurrent reservations against the same part as long as
        // callers run inside a transaction (the controllers do).
        static::creating(function ($item) {
            $sparePart = SparePart::whereKey($item->spare_part_id)->lockForUpdate()->first();
            if (! $sparePart) {
                throw new \RuntimeException('Spare part not found for counter invoice item.');
            }
            if ($item->quantity > $sparePart->current_stock_qty) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$sparePart->part_name}'. Requested {$item->quantity}, only {$sparePart->current_stock_qty} available."
                );
            }

            // The price is always sourced from the part master; a client can never override it.
            $item->mrp = $sparePart->mrp;
            $item->resolvedSparePart = $sparePart;
        });

        // Deduct stock when created
        static::created(function ($item) {
            $sparePart = $item->resolvedSparePart
                ?? SparePart::whereKey($item->spare_part_id)->lockForUpdate()->first();

            if ($sparePart) {
                $sparePart->decrement('current_stock_qty', $item->quantity);
                $item->resolvedSparePart = null;
            }
        });

        // Return stock when deleted
        static::deleting(function ($item) {
            $sparePart = SparePart::whereKey($item->spare_part_id)->lockForUpdate()->first();
            if ($sparePart) {
                $sparePart->increment('current_stock_qty', $item->quantity);
            }
        });
    }

    public function counterInvoice()
    {
        return $this->belongsTo(CounterInvoice::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }
}
