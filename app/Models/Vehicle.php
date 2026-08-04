<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'registration_no', 'chassis_no', 'motor_battery_serial_no',
        'vehicle_brand', 'model', 'battery_type', 'battery_capacity',
        'purchase_date', 'warranty_valid_till', 'current_odometer_km',
        'color', 'remarks',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_valid_till' => 'date',
        'battery_capacity' => 'decimal:2',
        'current_odometer_km' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
