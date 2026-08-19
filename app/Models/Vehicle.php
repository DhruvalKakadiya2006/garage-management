<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'vehicle_model_id', 'registration_no', 'chassis_no',
        'motor_battery_serial_no', 'is_non_rto', 'current_odometer_km', 'color', 'remarks', 'external_ref',
    ];

    protected $casts = [
        'is_non_rto' => 'boolean',
        'current_odometer_km' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class);
    }
}
