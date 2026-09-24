<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMonthlyInvoiceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-monthly-invoice-reminders 
                            {--type=initial : The reminder type: initial (1st of month) or reminder (5th of month)}
                            {--month= : Override billing month (1-12)}
                            {--year= : Override billing year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch automated monthly invoice notifications and reminders to guardians (1st and 5th of each month)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $type = $this->option('type') ?: 'initial';
        $month = $this->option('month') ? (int) $this->option('month') : (int) now()->format('n');
        $year = $this->option('year') ? (int) $this->option('year') : (int) now()->format('Y');

        // Check 10-month academic cycle: Summer break in July (7) and August (8)
        if (in_array($month, [7, 8], true)) {
            $this->info("Month {$month} is July or August (summer break). Exiting cleanly for 10-month school cycle.");

            return Command::SUCCESS;
        }

        $this->info("Starting invoice notifications dispatch [Type: {$type}, Month: {$month}, Year: {$year}]...");

        $statuses = match ($type) {
            'reminder' => [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue],
            default => [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid],
        };

        $invoices = Invoice::where('billing_month', $month)
            ->where('billing_year', $year)
            ->whereIn('status', $statuses)
            ->with(['student.guardian', 'student.user', 'school'])
            ->get();

        $targetMonth = sprintf('%04d-%02d', $year, $month);
        $invoices = $invoices->filter(function ($invoice) use ($targetMonth) {
            if (! $invoice->school?->billing_start_date) {
                return true;
            }

            return $targetMonth >= Carbon::parse($invoice->school->billing_start_date)->format('Y-m');
        });

        if ($invoices->isEmpty()) {
            $this->info("No unpaid or pending invoices found for month {$month}/{$year}.");

            return Command::SUCCESS;
        }

        $dispatchedCount = 0;
        foreach ($invoices as $invoice) {
            $notificationService->sendInvoiceReminder($invoice, $type);
            $dispatchedCount++;
        }

        $this->info("Successfully dispatched {$dispatchedCount} {$type} invoice reminders.");

        return Command::SUCCESS;
    }
}
