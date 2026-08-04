<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent DB-level cascade from deleting job_card_parts without firing
     * the JobCardPart::deleting stock-return event. Children must be deleted
     * via Eloquent by the controller so stock is returned to inventory.
     */
    public function up(): void
    {
        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->dropForeign(['job_card_id']);
            $table->dropForeign(['spare_part_id']);

            $table->foreign('job_card_id')->references('id')->on('job_cards')->onDelete('restrict');
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->dropForeign(['job_card_id']);
            $table->dropForeign(['spare_part_id']);

            $table->foreign('job_card_id')->references('id')->on('job_cards')->onDelete('cascade');
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('cascade');
        });
    }
};
