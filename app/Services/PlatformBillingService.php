<?php

namespace App\Services;

use App\Enums\SchoolSubscriptionStatus;
use App\Enums\StudentStatus;
use App\Models\PlatformSubscriptionInvoice;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;

class PlatformBillingService
{
    /**
     * Bill a single school for a given month (YYYY-MM).
     */
    public function billSchoolForMonth(School $school, string $billingMonth): PlatformSubscriptionInvoice
    {
        $activeStudentsCount = $school->students()
            ->where('status', StudentStatus::Active)
            ->count();

        $rateApplied = (float) ($school->price_per_student ?? 1.50);
        $minimumCharge = (float) ($school->monthly_minimum_charge ?? 300.00);

        $calculatedAmount = $activeStudentsCount * $rateApplied;
        $totalAmount = max($calculatedAmount, $minimumCharge);

        $stripeInvoiceId = null;
        $status = 'unpaid';
        $paidAt = null;

        // Attempt Stripe charge if stripe_customer_id is set
        if (! empty($school->stripe_customer_id)) {
            $stripeResult = $this->chargeStripeCustomer($school, $totalAmount, $billingMonth);
            if ($stripeResult['success']) {
                $status = 'paid';
                $paidAt = now();
                $stripeInvoiceId = $stripeResult['invoice_id'];
            } else {
                $stripeInvoiceId = $stripeResult['invoice_id'] ?? null;
            }
        }

        $invoice = PlatformSubscriptionInvoice::updateOrCreate(
            [
                'school_id' => $school->id,
                'billing_month' => $billingMonth,
            ],
            [
                'active_students_count' => $activeStudentsCount,
                'rate_applied' => $rateApplied,
                'total_amount' => $totalAmount,
                'stripe_invoice_id' => $stripeInvoiceId,
                'status' => $status,
                'paid_at' => $paidAt,
            ]
        );

        Log::info("Platform subscription invoice generated for {$school->name} [Month: {$billingMonth}, Students: {$activeStudentsCount}, Total: {$totalAmount} MAD, Status: {$status}]");

        return $invoice;
    }

    /**
     * Bill all eligible active schools for a given month.
     *
     * @return Collection<int, PlatformSubscriptionInvoice>
     */
    public function billAllActiveSchools(string $billingMonth): Collection
    {
        $schools = School::where('is_active', true)
            ->where('subscription_status', '!=', SchoolSubscriptionStatus::Cancelled->value)
            ->get();

        $invoices = collect();

        foreach ($schools as $school) {
            $invoice = $this->billSchoolForMonth($school, $billingMonth);
            $invoices->push($invoice);
        }

        return $invoices;
    }

    /**
     * Attempt Stripe charge via SDK or direct API call.
     *
     * @return array{success: bool, invoice_id: ?string, error: ?string}
     */
    protected function chargeStripeCustomer(School $school, float $amount, string $billingMonth): array
    {
        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');

        // If Stripe SDK is available and configured
        if (! empty($stripeSecret) && class_exists(Stripe::class)) {
            try {
                Stripe::setApiKey($stripeSecret);
                $stripeInvoice = Invoice::create([
                    'customer' => $school->stripe_customer_id,
                    'auto_advance' => true,
                    'collection_method' => 'charge_automatically',
                    'description' => "School SaaS Subscription - {$school->name} ({$billingMonth})",
                ]);

                InvoiceItem::create([
                    'customer' => $school->stripe_customer_id,
                    'invoice' => $stripeInvoice->id,
                    'amount' => (int) ($amount * 100), // cents/centimes
                    'currency' => 'mad',
                    'description' => "Monthly Platform Usage Fee ({$billingMonth})",
                ]);

                $paidInvoice = $stripeInvoice->pay();

                return [
                    'success' => $paidInvoice->status === 'paid',
                    'invoice_id' => $paidInvoice->id,
                    'error' => null,
                ];
            } catch (\Exception $e) {
                Log::warning("Stripe charge failed for school {$school->id}: {$e->getMessage()}");

                return [
                    'success' => false,
                    'invoice_id' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Test / offline mode or Stripe API mock
        $mockInvoiceId = 'in_mock_'.Str::lower(Str::random(14));

        return [
            'success' => true,
            'invoice_id' => $mockInvoiceId,
            'error' => null,
        ];
    }
}
