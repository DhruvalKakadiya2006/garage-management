<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_no', 'estimate_date', 'customer_id', 'vehicle_id', 'valid_until',
        'status', 'discount_percent', 'discount_amount', 'gst_applicable',
        'cgst_amount', 'sgst_amount', 'round_off_amount', 'grand_total', 'notes', 'job_card_id',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'valid_until' => 'date',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'gst_applicable' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($estimate) {
            if (empty($estimate->estimate_no)) {
                $estimate->estimate_no = self::generateEstimateNo();
            }
        });
    }

    public static function generateEstimateNo()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? (int) substr($latest->estimate_no, 4) + 1 : 1;
        return 'EST-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parts()
    {
        return $this->hasMany(EstimatePart::class);
    }

    public function labours()
    {
        return $this->hasMany(EstimateLabour::class);
    }

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class, 'job_card_id');
    }
}
