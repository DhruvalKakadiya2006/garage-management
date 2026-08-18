<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A job card is only "billed" once updateAssign computes and persists its
     * totals, so grand_total must stay NULL on freshly created cards — its
     * presence is the model's single source of truth for the billed state.
     * The old column defaulted to 0, which made every fresh card look billed.
     * Existing rows that were never billed (grand_total 0, no line items and
     * no payments) are normalised back to NULL.
     */
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->decimal('grand_total', 10, 2)->nullable()->change();
        });

        DB::table('job_cards')
            ->where('grand_total', 0)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('job_card_parts')
                ->whereColumn('job_card_parts.job_card_id', 'job_cards.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('job_card_labours')
                ->whereColumn('job_card_labours.job_card_id', 'job_cards.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('payments')
                ->whereColumn('payments.payable_id', 'job_cards.id')
                ->where('payments.payable_type', 'App\Models\JobCard'))
            ->update(['grand_total' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('job_cards')->whereNull('grand_total')->update(['grand_total' => 0]);

        Schema::table('job_cards', function (Blueprint $table) {
            $table->decimal('grand_total', 10, 2)->default(0)->change();
        });
    }
};
