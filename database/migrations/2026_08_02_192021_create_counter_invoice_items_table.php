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
        Schema::create('counter_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_invoice_id')->constrained('counter_invoices')->onDelete('cascade');
            $table->foreignId('spare_part_id')->constrained('spare_parts')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('mrp', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_invoice_items');
    }
};
