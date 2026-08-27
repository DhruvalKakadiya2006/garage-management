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
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_name');
            $table->string('part_code')->unique();
            $table->string('category')->nullable();
            $table->enum('unit', ['pcs', 'set', 'meter', 'kg']);
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('mrp', 10, 2);
            $table->decimal('labor_charge', 10, 2)->default(0);
            $table->string('hsn_code')->nullable();
            $table->decimal('gst_rate', 5, 2)->nullable();
            $table->integer('current_stock_qty')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
