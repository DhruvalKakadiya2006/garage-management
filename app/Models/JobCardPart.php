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
        });

        // Deduct stock when created
        static::created(function ($jobCardPart) {
            $sparePart = SparePart::find($jobCardPart->spare_part_id);
            if ($sparePart) {
                $sparePart->decrement('current_stock_qty', $jobCardPart->quantity);
            }
        });

        // Adjust stock when updated (handles both quantity and spare part changes)
        static::updating(function ($jobCardPart) {
            $oldPartId = (int) $jobCardPart->getOriginal('spare_part_id');
            $newPartId = (int) $jobCardPart->spare_part_id;
            $oldQuantity = (int) $jobCardPart->getOriginal('quantity');
            $newQuantity = (int) $jobCardPart->quantity;

            // Same part: only the quantity changed
            if ($oldPartId === $newPartId) {
                $difference = $newQuantity - $oldQuantity;
                if ($difference === 0) {
                    return;
                }
                $sparePart = SparePart::find($newPartId);
                if (!$sparePart) {
                    throw new \RuntimeException('Spare part not found for job card part.');
                }
                if ($difference > 0 && $difference > $sparePart->current_stock_qty) {
                    throw new \RuntimeException(
                        "Insufficient stock for '{$sparePart->part_name}'. Requested {$newQuantity}, only " . ($sparePart->current_stock_qty + $oldQuantity) . " available."
                    );
                }
                if ($difference > 0) {
                    $sparePart->decrement('current_stock_qty', $difference);
                } else {
                    $sparePart->increment('current_stock_qty', abs($difference));
                }
                return;
            }

            // Part swapped: return the old quantity to the old part, deduct from the new part
            $oldPart = SparePart::find($oldPartId);
            $newPart = SparePart::find($newPartId);
            if (!$oldPart || !$newPart) {
                throw new \RuntimeException('Spare part not found for job card part.');
            }
            if ($newQuantity > $newPart->current_stock_qty) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$newPart->part_name}'. Requested {$newQuantity}, only {$newPart->current_stock_qty} available."
                );
            }
            $oldPart->increment('current_stock_qty', $oldQuantity);
            $newPart->decrement('current_stock_qty', $newQuantity);
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
