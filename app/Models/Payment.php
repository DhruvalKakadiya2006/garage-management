<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payable_id',
        'payable_type',
        'amount',
        'payment_mode',
        'payment_date',
        'reference_no',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the parent payable model (JobCard or CounterInvoice).
     */
    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * Human-facing document type ('job_card' / 'counter_invoice') for exports.
     */
    public function getDocumentTypeAttribute(): ?string
    {
        if ($this->payable instanceof JobCard) {
            return 'job_card';
        }

        if ($this->payable instanceof CounterInvoice) {
            return 'counter_invoice';
        }

        return null;
    }

    /**
     * Document number of the payable (job card no / invoice no) for exports.
     */
    public function getDocumentNoAttribute(): ?string
    {
        $payable = $this->payable;

        if ($payable instanceof JobCard) {
            return $payable->job_card_no;
        }

        if ($payable instanceof CounterInvoice) {
            return $payable->invoice_no;
        }

        return null;
    }

    /**
     * Best human-readable customer label for the payable, for exports.
     */
    public function getCustomerLabelAttribute(): ?string
    {
        $payable = $this->payable;

        if ($payable instanceof JobCard) {
            return $payable->customer?->display_name;
        }

        if ($payable instanceof CounterInvoice) {
            return $payable->customer_id
                ? ($payable->customer?->display_name)
                : ($payable->walkin_name ?? 'Walk-in');
        }

        return null;
    }
}
