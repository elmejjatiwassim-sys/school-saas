<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\PlatformBillingService;
use Illuminate\Console\Command;

class BillSchoolsMonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:bill-schools-monthly
                            {--month= : Override billing month (1-12)}
                            {--year= : Override billing year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate automated monthly platform subscription invoices for schools based on active student count';

    /**
     * Execute the console command.
     */
    public function handle(PlatformBillingService $billingService): int
    {
        $month = $this->option('month') ? (int) $this->option('month') : (int) now()->format('n');
        $year = $this->option('year') ? (int) $this->option('year') : (int) now()->format('Y');

        // Check 10-month academic cycle: Summer break in July (7) and August (8)
        if (in_array($month, [7, 8], true)) {
            $this->info("Month {$month} is July or August (summer break). Exiting cleanly for 10-month school cycle.");

            return Command::SUCCESS;
        }

        $billingMonth = sprintf('%04d-%02d', $year, $month);
        $this->info("Starting platform subscription billing for month: {$billingMonth}...");

        $invoices = $billingService->billAllActiveSchools($billingMonth);

        // Reset AI monthly messages usage for schools on 1st of each active academic month
        School::query()->update(['ai_messages_used_this_month' => 0]);
        $this->info("Reset AI Copilot monthly usage (ai_messages_used_this_month = 0) for all schools for {$billingMonth}.");

        $this->info("Successfully processed platform billing for {$invoices->count()} schools for {$billingMonth}.");

        return Command::SUCCESS;
    }
}
