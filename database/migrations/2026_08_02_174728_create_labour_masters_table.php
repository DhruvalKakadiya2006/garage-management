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
        Schema::create('labour_masters', function (Blueprint $table) {
            $table->id();
            $table->string('service_name');
            $table->enum('category', ['battery', 'motor', 'brakes', 'electrical', 'general', 'other']);
            $table->decimal('standard_rate', 10, 2);
            $table->boolean('taxable')->default(true);
            $table->string('hsn_sac_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labour_masters');
    }
};
