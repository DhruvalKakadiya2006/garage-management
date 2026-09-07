<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix money columns to follow project convention:
     * - decimal(10,2)
     * - NOT NULL
     * - DEFAULT 0
     *
     * Violations found during audit (CORRECTED):
     * - spare_parts.purchase_price: NOT NULL but no default
     * - job_card_parts.labor_charge: NOT NULL but no default
     * - job_card_parts.line_total: NOT NULL but no default
     * - job_card_labours.line_total: NOT NULL but no default
     * - counter_invoice_items.line_total: NOT NULL but no default
     * - payments.amount: NOT NULL but no default
     *
     * EXCLUDED:
     * - job_cards.grand_total: nullable is INTENTIONAL (NULL = unbilled, non-NULL = billed)
     *   This is the single source of truth for is_billed state.
     */
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->decimal('labor_charge', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('line_total', 10, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->decimal('line_total', 10, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->decimal('line_total', 10, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(false)->default(0)->change();
        });
    }

    /**
     * Reverse the money column fixes.
     */
    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable(false)->default(null)->change();
        });

        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->decimal('labor_charge', 10, 2)->nullable(false)->default(null)->change();
            $table->decimal('line_total', 10, 2)->nullable(false)->default(null)->change();
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->decimal('line_total', 10, 2)->nullable(false)->default(null)->change();
        });

        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->decimal('line_total', 10, 2)->nullable(false)->default(null)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(false)->default(null)->change();
        });
    }
};
