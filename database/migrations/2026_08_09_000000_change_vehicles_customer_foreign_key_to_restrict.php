<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer cannot be deleted while any of their vehicles still exist,
     * matching what the customers delete confirmation dialog already claims
     * ("related vehicles and documents will be protected"). Replace cascade
     * with restrict on the vehicles.customer_id FK so a customer with any
     * vehicle (job card history or not) is blocked from deletion.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
};
