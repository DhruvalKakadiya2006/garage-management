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

    protected static function boot()
    {
        parent::boot();

        // Guard against overselling before the row is inserted
        static::creating(function ($item) {
            $sparePart = SparePart::find($item->spare_part_id);
            if (!$sparePart) {
                throw new \RuntimeException('Spare part not found for counter invoice item.');
            }
            if ($item->quantity > $sparePart->current_stock_qty) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$sparePart->part_name}'. Requested {$item->quantity}, only {$sparePart->current_stock_qty} available."
                );
            }
        });

        // Deduct stock when created
        static::created(function ($item) {
            $sparePart = SparePart::find($item->spare_part_id);
            if ($sparePart) {
                $sparePart->decrement('current_stock_qty', $item->quantity);
            }
        });

        // Adjust stock when updated (handles both quantity and spare part changes)
        static::updating(function ($item) {
            $oldPartId = (int) $item->getOriginal('spare_part_id');
            $newPartId = (int) $item->spare_part_id;
            $oldQuantity = (int) $item->getOriginal('quantity');
            $newQuantity = (int) $item->quantity;

            // Same part: only the quantity changed
            if ($oldPartId === $newPartId) {
                $difference = $newQuantity - $oldQuantity;
                if ($difference === 0) {
                    return;
                }
                $sparePart = SparePart::find($newPartId);
                if (!$sparePart) {
                    throw new \RuntimeException('Spare part not found for counter invoice item.');
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
                throw new \RuntimeException('Spare part not found for counter invoice item.');
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
        static::deleting(function ($item) {
            $sparePart = SparePart::find($item->spare_part_id);
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
