<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'registration_no', 'chassis_no', 'motor_battery_serial_no',
        'vehicle_brand', 'model', 'battery_type', 'is_non_rto',
        'current_odometer_km', 'color', 'remarks',
    ];

    protected $casts = [
        'is_non_rto' => 'boolean',
        'current_odometer_km' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
