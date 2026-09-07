<?php

namespace App\Console\Commands;

use App\Models\JobCard;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('legacy:mark-jobcards-paid')]
#[Description('Mark all existing job cards as fully paid (for legacy data import)')]
class MarkLegacyJobCardsPaid extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Marking all legacy job cards as fully paid...');

        // Get all billed job cards with outstanding balance
        $jobCards = JobCard::query()
            ->whereNotNull('grand_total')
            ->where('grand_total', '>', 0)
            ->withSum('payments', 'amount')
            ->get()
            ->filter(fn ($jc) => $jc->outstanding > 0);

        if ($jobCards->isEmpty()) {
            $this->info('No job cards with outstanding balance found.');

            return 0;
        }

        $this->info("Found {$jobCards->count()} job cards with outstanding balance.");

        $bar = $this->output->createProgressBar($jobCards->count());
        $bar->start();

        $created = 0;
        $totalAmount = 0;

        DB::transaction(function () use ($jobCards, &$created, &$totalAmount, $bar) {
            foreach ($jobCards as $jobCard) {
                $outstanding = $jobCard->outstanding;

                Payment::create([
                    'payable_type' => JobCard::class,
                    'payable_id' => $jobCard->id,
                    'amount' => $outstanding,
                    'payment_mode' => 'cash',
                    'payment_date' => $jobCard->job_date->toDateString(),
                    'reference_no' => 'LEGACY-IMPORT',
                    'notes' => 'Payment auto-created for legacy data import',
                ]);

                $created++;
                $totalAmount += $outstanding;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Created {$created} payments");
        $this->info('✓ Total amount: Rs. '.number_format($totalAmount, 2));
        $this->info('✓ All legacy job cards are now marked as fully paid');

        return 0;
    }
}
