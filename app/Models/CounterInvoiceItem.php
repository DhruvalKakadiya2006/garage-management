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

    /**
     * Model boot method - registers event listeners for price locking.
     *
     * Price Security:
     * - creating: Locks MRP from master to prevent price manipulation
     *
     * Stock Management: DISABLED
     * - Stock tracking has been disabled because the Suppliers module is not integrated.
     * - The current_stock_qty field remains in the database for future use.
     * - Invoice/Job Card line-item quantities continue to work normally for billing.
     */
    protected static function boot()
    {
        parent::boot();

        // Lock price from part master before inserting to prevent price manipulation
        static::creating(function ($item) {
            $sparePart = SparePart::lockForUpdate()->whereKey($item->spare_part_id)->first();
            if (! $sparePart) {
                throw new \RuntimeException('Spare part not found for counter invoice item.');
            }

            // The price is always sourced from the part master; a client can never override it.
            $item->mrp = $sparePart->mrp;
            $item->resolvedSparePart = $sparePart;

            // STOCK TRACKING DISABLED: No automatic stock decrement
            // Uncomment below to re-enable when Suppliers module is integrated:
            //
            // $quantity = (int) $item->quantity;
            // if ($sparePart->current_stock_qty < $quantity) {
            //     throw new \RuntimeException("Insufficient stock for '{$sparePart->part_name}'. Available: {$sparePart->current_stock_qty}, requested: {$quantity}.");
            // }
            // $sparePart->decrement('current_stock_qty', $quantity);
        });

        // STOCK TRACKING DISABLED: No automatic stock return on delete
        // Uncomment below to re-enable when Suppliers module is integrated:
        //
        // static::deleting(function ($item) {
        //     $sparePart = SparePart::lockForUpdate()->whereKey($item->spare_part_id)->first();
        //     if ($sparePart) {
        //         $sparePart->increment('current_stock_qty', (int) $item->quantity);
        //     }
        // });

        // STOCK TRACKING DISABLED: No automatic stock adjustment on update
        // Uncomment below to re-enable when Suppliers module is integrated:
        //
        // static::updating(function ($item) {
        //     if ($item->isDirty('quantity')) {
        //         $oldQty = (int) $item->getOriginal('quantity');
        //         $newQty = (int) $item->quantity;
        //         $delta = $newQty - $oldQty;
        //
        //         if ($delta != 0) {
        //             $sparePart = SparePart::lockForUpdate()->whereKey($item->spare_part_id)->first();
        //             if (! $sparePart) {
        //                 throw new \RuntimeException('Spare part not found for counter invoice item.');
        //             }
        //
        //             if ($delta > 0) {
        //                 // Increasing quantity - need more stock
        //                 if ($sparePart->current_stock_qty < $delta) {
        //                     throw new \RuntimeException("Insufficient stock for '{$sparePart->part_name}'. Available: {$sparePart->current_stock_qty}, need {$delta} more.");
        //                 }
        //                 $sparePart->decrement('current_stock_qty', $delta);
        //             } else {
        //                 // Decreasing quantity - return stock
        //                 $sparePart->increment('current_stock_qty', abs($delta));
        //             }
        //         }
        //     }
        // });
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
