<?php

namespace App\Models;

use App\Enums\AcademicMonth;
use App\Enums\FeeInvoiceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'invoice_number',
        'type',
        'invoice_type',
        'billing_month',
        'billing_year',
        'title',
        'total_amount',
        'paid_amount',
        'due_date',
        'status',
        'payment_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'invoice_type' => FeeInvoiceType::class,
            'billing_month' => 'integer',
            'billing_year' => 'integer',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (blank($invoice->payment_token)) {
                $invoice->payment_token = (string) Str::uuid();
            }

            if (blank($invoice->invoice_type)) {
                $invoice->invoice_type = FeeInvoiceType::MonthlyFee;
            }

            if (blank($invoice->billing_year)) {
                $invoice->billing_year = (int) date('Y');
            }
        });

        static::saved(function (Invoice $invoice): void {
            if ($invoice->type === InvoiceType::AnnualPackage && $invoice->status === InvoiceStatus::Paid) {
                $invoice->settleAnnualMonthlyInvoices();
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected function remainingAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, round((float) $this->total_amount - (float) $this->paid_amount, 2)),
        );
    }

    public function recalculatePaidAmountAndStatus(): void
    {
        $shouldSettleAnnual = false;

        DB::transaction(function () use (&$shouldSettleAnnual): void {
            $invoice = static::where('id', $this->id)->lockForUpdate()->first();
            if (! $invoice) {
                return;
            }

            $totalPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $invoice->paid_amount = $totalPaid;

            if ($totalPaid <= 0) {
                $invoice->status = ($invoice->due_date && $invoice->due_date->isPast())
                    ? InvoiceStatus::Overdue
                    : InvoiceStatus::Unpaid;
            } elseif ($totalPaid < (float) $invoice->total_amount) {
                $invoice->status = InvoiceStatus::PartiallyPaid;
            } else {
                $invoice->status = InvoiceStatus::Paid;
                if ($invoice->type === InvoiceType::AnnualPackage) {
                    $shouldSettleAnnual = true;
                }
            }

            $invoice->saveQuietly();
            $this->paid_amount = $invoice->paid_amount;
            $this->status = $invoice->status;
        });

        if ($shouldSettleAnnual) {
            $this->settleAnnualMonthlyInvoices();
        }
    }

    public function settleAnnualMonthlyInvoices(): void
    {
        if ($this->type !== InvoiceType::AnnualPackage || $this->status !== InvoiceStatus::Paid) {
            return;
        }

        $student = $this->student;
        $academicYear = $this->academicYear;
        if (! $student || ! $academicYear) {
            return;
        }

        $startYear = $academicYear->start_date ? Carbon::parse($academicYear->start_date)->year : (int) explode('-', $academicYear->name)[0];
        $endYear = $academicYear->end_date ? Carbon::parse($academicYear->end_date)->year : ($startYear + 1);

        // Find the monthly tuition amount from line items (or divide by 10 or default to 1500)
        $tuitionItem = $this->items()->where(function ($q) {
            $q->where('description', 'like', '%Tuition%')
                ->orWhere('description', 'like', '%واجب%')
                ->orWhere('description', 'like', '%Scolarité%');
        })->first();

        $monthlyTuition = 1500.00;
        if ($tuitionItem) {
            $monthlyTuition = $tuitionItem->quantity == 10
                ? round((float) $tuitionItem->amount, 2)
                : round((float) ($tuitionItem->amount * $tuitionItem->quantity) / 10, 2);
        }

        $masterPayment = $this->payments()->latest('payment_date')->first();
        $paymentMethod = $masterPayment?->payment_method ?? PaymentMethod::Cash;
        $paymentDate = $masterPayment?->payment_date ? $masterPayment->payment_date->toDateString() : now()->toDateString();

        foreach (AcademicMonth::academicCycleMonths() as $monthEnum) {
            $month = $monthEnum->value;
            $year = in_array($month, [9, 10, 11, 12], true) ? $startYear : $endYear;
            $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

            $monthlyInvoice = Invoice::firstOrCreate(
                [
                    'school_id' => $this->school_id,
                    'student_id' => $this->student_id,
                    'academic_year_id' => $this->academic_year_id,
                    'billing_month' => $month,
                    'billing_year' => $year,
                ],
                [
                    'type' => InvoiceType::Monthly,
                    'invoice_number' => "INV-{$year}{$monthPadded}-{$student->id}-".strtoupper(Str::random(4)),
                    'title' => 'واجب شهر '.$monthEnum->getArabicName().' '.$year,
                    'total_amount' => $monthlyTuition,
                    'paid_amount' => 0.00,
                    'due_date' => "{$year}-{$monthPadded}-05",
                    'status' => InvoiceStatus::Unpaid,
                ]
            );

            if ($monthlyInvoice->status !== InvoiceStatus::Paid) {
                $remaining = (float) $monthlyInvoice->remaining_amount;
                if ($remaining > 0) {
                    Payment::create([
                        'school_id' => $this->school_id,
                        'invoice_id' => $monthlyInvoice->id,
                        'amount' => $remaining,
                        'payment_date' => $paymentDate,
                        'payment_method' => $paymentMethod,
                        'receipt_number' => "ANN-REF-{$this->id}-M{$month}-".strtoupper(Str::random(4)),
                        'notes' => "Settled via Annual Package Invoice #{$this->invoice_number}",
                    ]);
                } else {
                    $monthlyInvoice->status = InvoiceStatus::Paid;
                    $monthlyInvoice->paid_amount = $monthlyInvoice->total_amount;
                    $monthlyInvoice->saveQuietly();
                }
            }
        }
    }
}
