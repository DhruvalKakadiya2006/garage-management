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
        Schema::create('job_card_labours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained('job_cards')->onDelete('cascade');
            $table->foreignId('labour_master_id')->constrained('labour_masters')->onDelete('cascade');
            $table->foreignId('technician_id')->nullable()->constrained('employees')->onDelete('set null');
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
        Schema::dropIfExists('job_card_labours');
    }
};
