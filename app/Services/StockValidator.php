<?php

namespace App\Services;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Model;

class StockValidator
{
    /**
     * Check that every requested line quantity can be covered by current stock,
     * accounting for the quantity a document already reserves for the same part.
     *
     * @param array       $lines    Submitted lines: [['spare_part_id' => int, 'quantity' => int], ...]
     * @param Model|null  $document Document whose line-item relation reserves stock (null when creating).
     * @param string      $relation Line-item relation name on the document ('parts' | 'items').
     * @return array<string, string> Validation errors keyed as "<relation>.<line>.quantity"
     */
    public function validate(array $lines, ?Model $document, string $relation): array
    {
        $errors = [];

        if (empty($lines)) {
            return $errors;
        }

        // Aggregate requested quantity per part across all lines in this request
        $usageByPart = [];
        foreach ($lines as $index => $line) {
            $partId = (int) $line['spare_part_id'];
            $usageByPart[$partId] = ($usageByPart[$partId] ?? 0) + (int) $line['quantity'];
        }

        foreach ($usageByPart as $partId => $requestedQty) {
            $sparePart = SparePart::find($partId);
            if (! $sparePart) {
                continue;
            }

            $available = (int) $sparePart->current_stock_qty;
            if ($document) {
                // Quantity this document already reserves for the same part
                $available += (int) $document->{$relation}()->where('spare_part_id', $partId)->sum('quantity');
            }

            if ($requestedQty > $available) {
                $index = collect($lines)->search(fn ($line) => (int) $line['spare_part_id'] === $partId);
                $errors["{$relation}.{$index}.quantity"] = "Insufficient stock for '{$sparePart->part_name}'. Requested {$requestedQty}, only {$available} available.";
            }
        }

        return $errors;
    }
}
