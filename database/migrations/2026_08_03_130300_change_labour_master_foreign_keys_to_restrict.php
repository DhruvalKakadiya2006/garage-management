<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labour services can never be deleted while line items link back to them
     * (rule 50). Replace cascade with restrict on every document FK.
     */
    public function up(): void
    {
        Schema::table('estimate_labours', function (Blueprint $table) {
            $table->dropForeign(['labour_master_id']);
            $table->foreign('labour_master_id')->references('id')->on('labour_masters')->onDelete('restrict');
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->dropForeign(['labour_master_id']);
            $table->foreign('labour_master_id')->references('id')->on('labour_masters')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimate_labours', function (Blueprint $table) {
            $table->dropForeign(['labour_master_id']);
            $table->foreign('labour_master_id')->references('id')->on('labour_masters')->onDelete('cascade');
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->dropForeign(['labour_master_id']);
            $table->foreign('labour_master_id')->references('id')->on('labour_masters')->onDelete('cascade');
        });
    }
};
