<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterInvoice extends Model
{
    use HasFactory;

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
            $invoice->payments()->delete();
        });
    }

    public static function generateInvoiceNo()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? (int) substr($latest->invoice_no, 3) + 1 : 1;
        return 'CI-' . str_pad($number, 4, '0', STR_PAD_LEFT);
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
        return max(0, $this->grand_total - $this->amount_paid);
    }

    /**
     * Get the derived payment status label.
     */
    public function getPaymentStatusLabelAttribute()
    {
        if ($this->outstanding == 0 && $this->amount_paid > 0) {
            return 'paid';
        } elseif ($this->amount_paid > 0 && $this->outstanding > 0) {
            return 'partially_paid';
        }
        return 'unpaid';
    }
}
