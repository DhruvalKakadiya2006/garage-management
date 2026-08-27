<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer Model
 *
 * Represents a customer who owns vehicles and receives services.
 * A customer can have multiple vehicles, job cards, counter invoices, and payments.
 *
 * Display Name: Auto-generated from first_name + last_name + surname if not provided.
 * This ensures every customer has a human-readable identifier for UI display.
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'surname', 'company_name', 'display_name', 'mobile', 'alt_mobile', 'email',
        'address', 'city', 'gstin', 'external_ref',
    ];

    /**
     * Model boot method - registers event listeners.
     *
     * Auto-generates display_name from name components when not explicitly provided.
     * This ensures search and display always have a consistent name format.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($customer) {
            // Auto-generate display_name if empty, combining all name parts
            // Multiple spaces are collapsed to single space for clean display
            if (empty($customer->display_name)) {
                $customer->display_name = trim(preg_replace('/\s+/', ' ',
                    "{$customer->first_name} {$customer->last_name} {$customer->surname}"
                ));
            }
        });
    }

    /**
     * One customer can own multiple vehicles.
     *
     * @return HasMany
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * All job cards for services performed on this customer's vehicles.
     *
     * @return HasMany
     */
    public function jobCards()
    {
        return $this->hasMany(JobCard::class);
    }

    /**
     * All counter invoices (parts-only sales) for this customer.
     *
     * @return HasMany
     */
    public function counterInvoices()
    {
        return $this->hasMany(CounterInvoice::class);
    }
}
