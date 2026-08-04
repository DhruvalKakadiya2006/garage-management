<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'mobile', 'alt_mobile', 'email',
        'address', 'city', 'pincode', 'gstin', 'customer_type',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}