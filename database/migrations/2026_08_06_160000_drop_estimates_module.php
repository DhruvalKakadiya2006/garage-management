<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The standalone Estimate module has been replaced by the Job Card's
     * built-in Invoice/Estimate PDF generation (see AI_AGENT_CONTEXT.md §8).
     * We drop the source_estimate_id column first (removing its FK) and only
     * then remove the estimate tables themselves.
     */
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_estimate_id');
        });

        Schema::dropIfExists('estimate_labours');
        Schema::dropIfExists('estimate_parts');
        Schema::dropIfExists('estimates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_no')->unique();
            $table->date('estimate_date');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'approved', 'rejected', 'expired'])->default('draft');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->boolean('gst_applicable')->default(false);
            $table->decimal('cgst_amount', 10, 2)->default(0);
            $table->decimal('sgst_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('job_card_id')->nullable()->constrained('job_cards')->onDelete('set null');
            $table->timestamps();
        });

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

        Schema::create('estimate_labours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->onDelete('cascade');
            $table->foreignId('labour_master_id')->constrained('labour_masters')->onDelete('cascade');
            $table->decimal('rate', 10, 2);
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->foreignId('source_estimate_id')->nullable()->constrained('estimates')->onDelete('set null');
        });
    }
};
