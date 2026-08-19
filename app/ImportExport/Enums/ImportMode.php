<?php

namespace App\ImportExport\Enums;

/**
 * How an import matches rows against existing records.
 */
enum ImportMode: string
{
    /** Create records that do not exist; existing records are flagged and skipped. */
    case CREATE_ONLY = 'create_only';

    /** Match an existing record via the defined import keys and update it; otherwise create. */
    case CREATE_OR_UPDATE = 'create_or_update';

    public function label(): string
    {
        return match ($this) {
            self::CREATE_ONLY => 'Create only',
            self::CREATE_OR_UPDATE => 'Create or update',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CREATE_ONLY => 'Creates new records and skips any that already exist.',
            self::CREATE_OR_UPDATE => 'Updates existing records matched by import key, and creates new ones.',
        };
    }
}