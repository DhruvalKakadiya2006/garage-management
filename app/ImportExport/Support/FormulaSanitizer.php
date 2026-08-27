<?php

namespace App\ImportExport\Support;

/**
 * CSV injection ("formula injection") protection for exported files.
 *
 * Any exported string cell that begins with a spreadsheet formula indicator
 * (=, +, -, @) or a control character is prefixed with an apostrophe so the
 * value is interpreted as literal text when opened in Excel/Sheets. Genuine
 * numeric values are left untouched so negative numbers and currency export
 * correctly.
 */
class FormulaSanitizer
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r", "\n"];

    public static function sanitize(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $value = (string) $value;

        // True numeric values (including "-5") are never a formula.
        if (is_numeric($value)) {
            return $value;
        }

        $first = $value === '' ? '' : $value[0];

        if (in_array($first, self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
