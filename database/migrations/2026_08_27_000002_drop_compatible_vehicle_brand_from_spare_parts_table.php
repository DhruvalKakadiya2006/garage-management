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
        if (Schema::hasColumn('spare_parts', 'compatible_vehicle_brand')) {
            Schema::table('spare_parts', function (Blueprint $table) {
                $table->dropColumn('compatible_vehicle_brand');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->string('compatible_vehicle_brand')->nullable();
        });
    }
};
