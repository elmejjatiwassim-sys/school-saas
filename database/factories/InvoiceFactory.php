<?php

namespace Database\Factories;

use App\Enums\AcademicMonth;
use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monthEnum = fake()->randomElement(AcademicMonth::academicCycleMonths());
        $month = $monthEnum->value;
        $year = in_array($month, [9, 10, 11, 12], true) ? 2026 : 2027;
        $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return [
            'school_id' => School::factory(),
            'student_id' => fn (array $attributes) => Student::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'invoice_number' => 'INV-'.$year.$monthPadded.'-'.fake()->unique()->numerify('#####'),
            'billing_month' => $month,
            'billing_year' => $year,
            'title' => 'واجب شهر '.$monthEnum->getArabicName().' '.$year,
            'invoice_type' => 'monthly_fee',
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
            'due_date' => "{$year}-{$monthPadded}-05",
            'status' => InvoiceStatus::Unpaid,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['total_amount'] ?? 1500.00,
            'status' => InvoiceStatus::Paid,
        ]);
    }
}
