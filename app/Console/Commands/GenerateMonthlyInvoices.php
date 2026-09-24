<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\School;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-monthly-invoices
                            {--month= : Academic billing month (1-12)}
                            {--year= : Billing year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly tuition invoices for active schools, skipping past months prior to billing_start_date and summer break';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceGenerationService $invoiceService): int
    {
        $month = $this->option('month') ? (int) $this->option('month') : (int) now()->format('n');
        $year = $this->option('year') ? (int) $this->option('year') : (int) now()->format('Y');

        if (in_array($month, [7, 8], true)) {
            $this->info("Month {$month} is July/August (summer break). Skipping 10-month academic cycle.");

            return Command::SUCCESS;
        }

        $targetMonthStr = sprintf('%04d-%02d', $year, $month);
        $this->info("Starting monthly invoice generation for {$targetMonthStr}...");

        $schools = School::where('is_active', true)->get();
        $totalCreated = 0;

        foreach ($schools as $school) {
            // Check: do NOT generate monthly tuition invoices for months prior to the school's billing_start_date
            if ($school->billing_start_date) {
                $schoolStartMonth = Carbon::parse($school->billing_start_date)->format('Y-m');
                if ($targetMonthStr < $schoolStartMonth) {
                    $this->line("Skipping school [{$school->name}]: {$targetMonthStr} is prior to billing start date ({$school->billing_start_date->format('Y-m-d')}).");

                    continue;
                }
            }

            $currentYear = AcademicYear::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();

            if (! $currentYear) {
                continue;
            }

            $defaultFee = (float) (FeeType::where('school_id', $school->id)
                ->where('is_recurring_monthly', true)
                ->value('default_amount') ?? 1500.00);

            $students = $school->students()->where('status', 'active')->get();
            foreach ($students as $student) {
                $totalCreated += $invoiceService->generateForStudent($student, $currentYear, $defaultFee);
            }
        }

        $this->info("Generated {$totalCreated} invoices across active schools for {$targetMonthStr}.");

        return Command::SUCCESS;
    }
}
