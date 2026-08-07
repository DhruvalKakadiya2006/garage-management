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
        // Complaints are now description-only (see AI_AGENT_CONTEXT.md §6). The charge field is permanently removed.
        Schema::table('job_card_complaints', function (Blueprint $table) {
            $table->dropColumn('charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_card_complaints', function (Blueprint $table) {
            $table->decimal('charge', 10, 2)->default(0);
        });
    }
};
