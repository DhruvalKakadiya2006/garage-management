<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complaints are free-text only (description + charge) — the Complaint
     * Master lookup feature was deliberately dropped (rule 51). Remove the
     * dead complaint_master_id column left behind by that decision.
     */
    public function up(): void
    {
        Schema::table('job_card_complaints', function (Blueprint $table) {
            $table->dropColumn('complaint_master_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_card_complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('complaint_master_id')->nullable();
        });
    }
};
