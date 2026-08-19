<?php

namespace App\ImportExport\Support;

use App\ImportExport\ValidationIssue;

/**
 * Coerces raw spreadsheet cell values (always strings) into typed values
 * matching a column spec, without ever modifying the uploaded file.
 *
 * On failure it returns a ValidationIssue (error) so the caller can report
 * exactly what was supplied and why it could not be interpreted.
 */
class ValueNormalizer
{
    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm/d/Y', 'd.m.Y'];

    private const DATETIME_FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'];

    private const TIME_FORMATS = ['H:i:s', 'H:i'];

    /**
     * Coerce a raw value according to a column spec.
     *
     * @param array{type?: string, enum?: array|null, max_length?: int} $spec
     * @return array{0: mixed, 1: ValidationIssue|null}
     */
    public static function coerce(mixed $raw, array $spec, int $rowNumber): array
    {
        $type = $spec['type'] ?? 'string';
        $field = $spec['field'] ?? '';
        $label = $spec['label'] ?? $field;

        if ($raw === null) {
            return [null, null];
        }

        $value = is_string($raw) ? trim($raw) : $raw;

        if ($value === '') {
            return [null, null];
        }

        switch ($type) {
            case 'string':
                $value = (string) $value;
                $maxLength = $spec['max_length'] ?? null;
                if ($maxLength !== null && mb_strlen($value) > $maxLength) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must not exceed {$maxLength} characters."
                    )];
                }

                return [$value, null];

            case 'integer':
                if (! is_numeric($value) || (float) $value != (int) $value) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be a whole number."
                    )];
                }

                return [(int) $value, null];

            case 'decimal':
                if (! is_numeric($value)) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be a number."
                    )];
                }

                return [(float) $value, null];

            case 'boolean':
                $normalized = self::coerceBoolean($value);
                if ($normalized === null) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be true/false, yes/no or 1/0."
                    )];
                }

                return [$normalized, null];

            case 'date':
                $date = self::coerceDate($value, false);
                if ($date === null) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be a valid date (e.g. 2025-01-31 or 31/01/2025)."
                    )];
                }

                return [$date, null];

            case 'datetime':
                $date = self::coerceDate($value, true);
                if ($date === null) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be a valid date-time."
                    )];
                }

                return [$date, null];

            case 'time':
                $time = self::coerceTime($value);
                if ($time === null) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be a valid time (e.g. 14:30 or 14:30:00)."
                    )];
                }

                return [$time, null];

            case 'enum':
                $allowed = $spec['enum'] ?? [];
                if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                    return [null, ValidationIssue::error(
                        $rowNumber,
                        $field,
                        $raw,
                        "{$label} must be one of: ".implode(', ', $allowed).'.'
                    )];
                }

                return [(string) $value, null];
        }

        return [(string) $value, null];
    }

    /**
     * Human-friendly string for the preview of an interpreted value.
     */
    public static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    private static function coerceBoolean(string $value): ?bool
    {
        return match (mb_strtolower($value)) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => null,
        };
    }

    private static function coerceDate(string $value, bool $withTime): ?string
    {
        $formats = $withTime ? self::DATETIME_FORMATS : self::DATE_FORMATS;

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    continue;
                }

                return $date->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }

        // Fall back to strtotime for other formats Laravel-style validators accept.
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return $withTime ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts);
    }

    private static function coerceTime(string $value): ?string
    {
        foreach (self::TIME_FORMATS as $format) {
            $time = \DateTimeImmutable::createFromFormat($format, $value);
            if ($time !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    continue;
                }

                return $time->format('H:i:s');
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('H:i:s', $ts);
    }
}