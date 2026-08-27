<?php

namespace App\ImportExport\Enums;

/**
 * What the pipeline plans to do with a single row.
 */
enum RowAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case SKIP = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Create',
            self::UPDATE => 'Update',
            self::SKIP => 'Skip',
        };
    }
}
