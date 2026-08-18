<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guard against a discount that would push the grand total negative.
 *
 * The effective discount is whichever the document actually applies: a
 * discount_percent is converted to an amount (percent of subtotal), otherwise
 * the entered discount_amount is used directly. In both cases the applied
 * amount may never exceed the subtotal it carves into.
 */
class DiscountWithinSubtotal implements ValidationRule
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $discountPercent = 0,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subtotal = $this->subtotal;

        $effectiveDiscount = $this->discountPercent > 0
            ? ($subtotal * $this->discountPercent) / 100
            : (float) ($value ?? 0);

        if ($effectiveDiscount > $subtotal) {
            $fail('Discount cannot exceed the subtotal of '.config('garage.currency_symbol').number_format($subtotal, 2).'.');
        }
    }
}
