<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spare parts can never be deleted while estimate line items link back
     * to them (rule 50). job_card_parts and counter_invoice_items already use
     * restrict from earlier migrations.
     */
    public function up(): void
    {
        Schema::table('estimate_parts', function (Blueprint $table) {
            $table->dropForeign(['spare_part_id']);
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimate_parts', function (Blueprint $table) {
            $table->dropForeign(['spare_part_id']);
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('cascade');
        });
    }
};
