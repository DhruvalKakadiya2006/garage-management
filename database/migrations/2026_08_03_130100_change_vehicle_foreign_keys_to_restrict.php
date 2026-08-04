<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicles can never be deleted while estimates or job cards link back
     * to them (rule 50). Replace cascade with restrict on every document FK.
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('restrict');
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
        });
    }
};
