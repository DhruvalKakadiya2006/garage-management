<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterInvoice extends Model
{
    use HasFactory;

    public const INVOICE_NO_PREFIX = 'CI-';

    protected $fillable = [
        'invoice_no', 'invoice_date', 'customer_id', 'walkin_name', 'walkin_mobile',
        'gst_applicable', 'cgst_amount', 'sgst_amount', 'round_off_amount',
        'discount_percent', 'discount_amount', 'grand_total', 'payment_mode',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'gst_applicable' => 'boolean',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_no)) {
                $invoice->invoice_no = self::generateInvoiceNo();
            }
        });

        static::deleting(function ($invoice) {
            // The invoice row itself is hard-deleted, so its payment records
            // must be removed permanently too — a soft delete would leave
            // orphaned rows behind forever.
            $invoice->payments()->get()->each->forceDelete();
        });
    }

    /**
     * Generate the next sequential counter invoice number.
     *
     * Derived from the highest existing numeric suffix across every row (the
     * unique index on invoice_no spans all of them), so gaps and concurrent
     * writers cannot produce duplicates. On collision one writer receives a
     * QueryException instead of a silently duplicated number.
     */
    public static function generateInvoiceNo(): string
    {
        $maxSuffix = (int) self::query()
            ->selectRaw('MAX(CAST(substr(invoice_no, ?) AS INTEGER)) as max_suffix', [strlen(self::INVOICE_NO_PREFIX) + 1])
            ->value('max_suffix');

        $next = $maxSuffix + 1;

        while (self::where('invoice_no', self::formatInvoiceNo($next))->exists()) {
            $next++;
        }

        return self::formatInvoiceNo($next);
    }

    public static function formatInvoiceNo(int $number): string
    {
        return self::INVOICE_NO_PREFIX.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CounterInvoiceItem::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Get the total amount paid via all payments.
     *
     * Uses the eager-loaded payments_sum_amount (added via withSum) when the
     * model was loaded through an index/list query; otherwise falls back to a
     * live SUM query so single-record contexts stay correct.
     */
    public function getAmountPaidAttribute()
    {
        if (array_key_exists('payments_sum_amount', $this->attributes)) {
            return (float) ($this->attributes['payments_sum_amount'] ?? 0);
        }

        return $this->payments()->sum('amount');
    }

    /**
     * Get the outstanding amount (remaining balance).
     */
    public function getOutstandingAttribute()
    {
        return max(0, round((float) $this->grand_total, 2) - round((float) $this->amount_paid, 2));
    }

    /**
     * Get the derived payment status label.
     *
     * Comparisons round to cents first — raw float sums carry binary residue
     * that would flip fully paid documents to partially_paid.
     */
    public function getPaymentStatusLabelAttribute()
    {
        $amountPaid = round((float) $this->amount_paid, 2);
        $outstanding = round((float) $this->outstanding, 2);

        if ($outstanding == 0 && $amountPaid > 0) {
            return 'paid';
        } elseif ($amountPaid > 0 && $outstanding > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }
}
