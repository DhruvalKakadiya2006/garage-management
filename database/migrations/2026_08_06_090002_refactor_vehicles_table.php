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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('is_non_rto')->default(false)->after('model');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('registration_no')->nullable()->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('battery_capacity');
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('purchase_date');
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('warranty_valid_till');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('battery_capacity')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_valid_till')->nullable();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('registration_no')->nullable(false)->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('is_non_rto');
        });
    }
};
