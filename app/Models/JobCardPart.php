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

    protected static function boot()
    {
        parent::boot();

        // Guard against overselling before the row is inserted
        static::creating(function ($jobCardPart) {
            $sparePart = SparePart::find($jobCardPart->spare_part_id);
            if (!$sparePart) {
                throw new \RuntimeException('Spare part not found for job card part.');
            }
            if ($jobCardPart->quantity > $sparePart->current_stock_qty) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$sparePart->part_name}'. Requested {$jobCardPart->quantity}, only {$sparePart->current_stock_qty} available."
                );
            }

            // The price is always sourced from the part master; a client can never override it.
            $jobCardPart->mrp = $sparePart->mrp;
        });

        // Deduct stock when created
        static::created(function ($jobCardPart) {
            $sparePart = SparePart::find($jobCardPart->spare_part_id);
            if ($sparePart) {
                $sparePart->decrement('current_stock_qty', $jobCardPart->quantity);
            }
        });

        // Return stock when deleted
        static::deleting(function ($jobCardPart) {
            $sparePart = SparePart::find($jobCardPart->spare_part_id);
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
