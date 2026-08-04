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
        Schema::create('estimate_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->onDelete('cascade');
            $table->foreignId('spare_part_id')->constrained('spare_parts')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('mrp', 10, 2);
            $table->decimal('labor_charge', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_parts');
    }
};
