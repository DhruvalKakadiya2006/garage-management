<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_no', 'job_date', 'customer_id', 'vehicle_id', 'odometer_reading_km',
        'job_type', 'assigned_technician_id', 'supervisor_id',
        'promised_delivery_date', 'promised_delivery_time', 'status',
        'discount_percent', 'discount_amount', 'gst_applicable',
        'cgst_amount', 'sgst_amount', 'round_off_amount', 'grand_total',
        'remarks', 'invoice_generated_at', 'estimate_generated_at',
    ];

    protected $casts = [
        'job_date' => 'date',
        'promised_delivery_date' => 'date',
        'odometer_reading_km' => 'integer',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'gst_applicable' => 'boolean',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'invoice_generated_at' => 'datetime',
        'estimate_generated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($jobCard) {
            if (empty($jobCard->job_card_no)) {
                $jobCard->job_card_no = self::generateJobCardNo();
            }
        });

        static::deleting(function ($jobCard) {
            $jobCard->payments()->delete();
        });
    }

    public static function generateJobCardNo()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? (int) substr($latest->job_card_no, 3) + 1 : 1;
        return 'JC-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(Employee::class, 'assigned_technician_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function complaints()
    {
        return $this->hasMany(JobCardComplaint::class);
    }

    public function parts()
    {
        return $this->hasMany(JobCardPart::class);
    }

    public function labours()
    {
        return $this->hasMany(JobCardLabour::class);
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
