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
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('job_card_no')->unique();
            $table->date('job_date');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->integer('odometer_reading_km')->nullable();
            $table->enum('job_type', ['free_service', 'paid_service', 'warranty', 'amc', 'insurance_claim', 'breakdown']);
            $table->boolean('urgent')->default(false);
            $table->foreignId('assigned_technician_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->date('promised_delivery_date')->nullable();
            $table->time('promised_delivery_time')->nullable();
            $table->enum('status', ['received', 'in_progress', 'waiting_for_parts', 'ready_for_delivery', 'delivered', 'cancelled'])->default('received');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->boolean('gst_applicable')->default(false);
            $table->decimal('cgst_amount', 10, 2)->default(0);
            $table->decimal('sgst_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->nullable();
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->text('remarks')->nullable();
            $table->foreignId('source_estimate_id')->nullable()->constrained('estimates')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_cards');
    }
};
