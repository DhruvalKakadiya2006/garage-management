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
        if (Schema::hasColumn('labour_masters', 'estimated_time_minutes')) {
            Schema::table('labour_masters', function (Blueprint $table) {
                $table->dropColumn('estimated_time_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labour_masters', function (Blueprint $table) {
            $table->integer('estimated_time_minutes')->nullable();
        });
    }
};
