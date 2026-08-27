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
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('urgent');
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->timestamp('invoice_generated_at')->nullable();
            $table->timestamp('estimate_generated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('invoice_generated_at');
        });
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('estimate_generated_at');
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->boolean('urgent')->default(false);
        });
    }
};
