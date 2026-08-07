<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'surname', 'company_name', 'display_name', 'mobile', 'alt_mobile', 'email',
        'address', 'city', 'gstin', 'customer_type',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->display_name)) {
                $customer->display_name = trim(preg_replace('/\s+/', ' ',
                    "{$customer->first_name} {$customer->last_name} {$customer->surname}"
                ));
            }
        });
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function jobCards()
    {
        return $this->hasMany(JobCard::class);
    }

    public function counterInvoices()
    {
        return $this->hasMany(CounterInvoice::class);
    }
}