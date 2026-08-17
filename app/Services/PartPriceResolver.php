<?php

namespace App\Services;

use App\Models\SparePart;

/**
 * Resolves the authoritative MRP for spare part lines from the part master.
 *
 * Client-submitted mrp values are never trusted: every part line is priced
 * from SparePart::mrp at save time so the billed price can only ever come
 * from the spare part catalog.
 */
class PartPriceResolver
{
    /**
     * Overwrite the mrp on each part line with the master-record price.
     *
     * @param array $lines Raw part lines, each containing a 'spare_part_id'.
     * @return array The same lines keyed by index with 'mrp' taken from the catalog.
     */
    public function priceLines(array $lines): array
    {
        $parts = $this->partsByKey($lines);

        $priced = [];
        foreach ($lines as $index => $line) {
            $part = $parts[(int) ($line['spare_part_id'] ?? 0)] ?? null;
            $line['mrp'] = (float) ($part->mrp ?? 0);
            $priced[$index] = $line;
        }

        return $priced;
    }

    private function partsByKey(array $lines)
    {
        $ids = collect($lines)->pluck('spare_part_id')->unique()->filter();

        return $ids->isEmpty()
            ? collect()
            : SparePart::whereIn('id', $ids)->get()->keyBy('id');
    }
}
