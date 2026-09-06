<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_id', 'spare_part_id', 'quantity', 'mrp', 'labor_charge',
        'under_warranty', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'mrp' => 'decimal:2',
        'labor_charge' => 'decimal:2',
        'under_warranty' => 'boolean',
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
        static::creating(function ($jobCardPart) {
            $sparePart = SparePart::lockForUpdate()->whereKey($jobCardPart->spare_part_id)->first();
            if (! $sparePart) {
                throw new \RuntimeException('Spare part not found for job card part.');
            }

            // SECURITY: The price is always sourced from the part master;
            // a client can never override it. This prevents price manipulation.
            $jobCardPart->mrp = $sparePart->mrp;
            $jobCardPart->resolvedSparePart = $sparePart;

            // STOCK TRACKING DISABLED: No automatic stock decrement
            // Uncomment below to re-enable when Suppliers module is integrated:
            //
            // $quantity = (int) $jobCardPart->quantity;
            // if ($sparePart->current_stock_qty < $quantity) {
            //     throw new \RuntimeException("Insufficient stock for '{$sparePart->part_name}'. Available: {$sparePart->current_stock_qty}, requested: {$quantity}.");
            // }
            // $sparePart->decrement('current_stock_qty', $quantity);
        });

        // STOCK TRACKING DISABLED: No automatic stock return on delete
        // Uncomment below to re-enable when Suppliers module is integrated:
        //
        // static::deleting(function ($jobCardPart) {
        //     $sparePart = SparePart::lockForUpdate()->whereKey($jobCardPart->spare_part_id)->first();
        //     if ($sparePart) {
        //         $sparePart->increment('current_stock_qty', (int) $jobCardPart->quantity);
        //     }
        // });

        // STOCK TRACKING DISABLED: No automatic stock adjustment on update
        // Uncomment below to re-enable when Suppliers module is integrated:
        //
        // static::updating(function ($jobCardPart) {
        //     if ($jobCardPart->isDirty('quantity')) {
        //         $oldQty = (int) $jobCardPart->getOriginal('quantity');
        //         $newQty = (int) $jobCardPart->quantity;
        //         $delta = $newQty - $oldQty;
        //
        //         if ($delta != 0) {
        //             $sparePart = SparePart::lockForUpdate()->whereKey($jobCardPart->spare_part_id)->first();
        //             if (! $sparePart) {
        //                 throw new \RuntimeException('Spare part not found for job card part.');
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

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }
}
