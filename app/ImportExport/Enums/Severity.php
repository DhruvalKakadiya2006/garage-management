<?php

namespace App\ImportExport\Enums;

/**
 * Severity of a validation issue.
 */
enum Severity: string
{
    /** Cannot be safely imported. */
    case ERROR = 'error';

    /** Importable, but requires attention. */
    case WARNING = 'warning';

    /** Informational. */
    case INFO = 'info';
}
