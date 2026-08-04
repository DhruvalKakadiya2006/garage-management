<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('registration_no')->unique();
            $table->string('chassis_no')->nullable();
            $table->string('motor_battery_serial_no')->nullable();
            $table->string('vehicle_brand')->nullable();
            $table->string('model')->nullable();
            $table->enum('battery_type', ['lithium-ion', 'lead-acid', 'other'])->nullable();
            $table->decimal('battery_capacity', 10, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_valid_till')->nullable();
            $table->integer('current_odometer_km')->default(0);
            $table->string('color')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
