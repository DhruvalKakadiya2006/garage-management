<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

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
}
