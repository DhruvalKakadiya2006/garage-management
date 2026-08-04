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
        Schema::create('estimate_labours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->onDelete('cascade');
            $table->foreignId('labour_master_id')->constrained('labour_masters')->onDelete('cascade');
            $table->decimal('rate', 10, 2);
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_labours');
    }
};
