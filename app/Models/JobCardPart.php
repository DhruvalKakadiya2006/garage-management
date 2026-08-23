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
     * Model boot method - registers event listeners for stock management.
     *
     * Stock Flow:
     * 1. creating: Validates stock availability under a row lock and locks
     *    MRP from master
     * 2. created: Deducts quantity from spare_parts.current_stock_qty
     * 3. deleting: Returns quantity back to spare_parts.current_stock_qty
     *
     * This ensures stock is always accurately tracked and prevents overselling.
     * The CHECK constraint on spare_parts prevents negative stock at DB level.
     */
    protected static function boot()
    {
        parent::boot();

        // Guard against overselling before the row is inserted. The row lock
        // serialises concurrent reservations against the same part as long as
        // callers run inside a transaction (the controllers do).
        static::creating(function ($jobCardPart) {
            $sparePart = SparePart::whereKey($jobCardPart->spare_part_id)->lockForUpdate()->first();
            if (! $sparePart) {
                throw new \RuntimeException('Spare part not found for job card part.');
            }
            if ($jobCardPart->quantity > $sparePart->current_stock_qty) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$sparePart->part_name}'. Requested {$jobCardPart->quantity}, only {$sparePart->current_stock_qty} available."
                );
            }

            // SECURITY: The price is always sourced from the part master;
            // a client can never override it. This prevents price manipulation.
            $jobCardPart->mrp = $sparePart->mrp;
            $jobCardPart->resolvedSparePart = $sparePart;
        });

        // Deduct stock when created
        static::created(function ($jobCardPart) {
            $sparePart = $jobCardPart->resolvedSparePart
                ?? SparePart::whereKey($jobCardPart->spare_part_id)->lockForUpdate()->first();

            if ($sparePart) {
                $sparePart->decrement('current_stock_qty', $jobCardPart->quantity);
                $jobCardPart->resolvedSparePart = null;
            }
        });

        // Return stock when deleted (job card deleted or parts edited)
        static::deleting(function ($jobCardPart) {
            $sparePart = SparePart::whereKey($jobCardPart->spare_part_id)->lockForUpdate()->first();
            if ($sparePart) {
                $sparePart->increment('current_stock_qty', $jobCardPart->quantity);
            }
        });
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
