<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\InvoiceGenerationService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_10_months')
                ->label('Generate 10 Months Invoices (توليد فواتير 10 أشهر)')
                ->icon('heroicon-o-document-duplicate')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('scope')
                        ->label('Target Scope')
                        ->options([
                            'classroom' => 'Entire Classroom (فصل دراسي كامل)',
                            'student' => 'Single Student (تلميذ واحد)',
                        ])
                        ->default('classroom')
                        ->live()
                        ->required(),

                    Forms\Components\Select::make('classroom_id')
                        ->label('Classroom')
                        ->options(fn () => Classroom::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->pluck('name', 'id'))
                        ->visible(fn (Forms\Get $get): bool => $get('scope') === 'classroom')
                        ->required(fn (Forms\Get $get): bool => $get('scope') === 'classroom')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('student_id')
                        ->label('Student')
                        ->options(fn () => Student::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->get()->mapWithKeys(fn (Student $s) => [
                            $s->id => "{$s->first_name} {$s->last_name} (".($s->classroom?->name ?? 'No class').')',
                        ]))
                        ->visible(fn (Forms\Get $get): bool => $get('scope') === 'student')
                        ->required(fn (Forms\Get $get): bool => $get('scope') === 'student')
                        ->searchable(),

                    Forms\Components\Select::make('academic_year_id')
                        ->label('Academic Year')
                        ->options(fn () => AcademicYear::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->pluck('name', 'id'))
                        ->default(fn () => AcademicYear::where('school_id', Filament::getTenant()?->id)
                            ->where('is_current', true)
                            ->value('id'))
                        ->required(),

                    Forms\Components\TextInput::make('monthly_amount')
                        ->label('Monthly Tuition Amount')
                        ->numeric()
                        ->prefix('MAD')
                        ->default(fn () => FeeType::where('school_id', Filament::getTenant()?->id)
                            ->where('is_recurring_monthly', true)
                            ->value('default_amount') ?? 1500)
                        ->required()
                        ->minValue(1),

                    Forms\Components\TextInput::make('due_day')
                        ->label('Due Day of Each Month')
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(28)
                        ->required(),
                ])
                ->action(function (array $data, InvoiceGenerationService $service): void {
                    $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                    $amount = (float) $data['monthly_amount'];
                    $dueDay = (int) $data['due_day'];

                    if ($data['scope'] === 'classroom') {
                        $classroom = Classroom::findOrFail($data['classroom_id']);
                        $count = $service->generateForClassroom($classroom, $academicYear, $amount, $dueDay);
                        Notification::make()
                            ->title('Invoices Generated')
                            ->body("Successfully generated {$count} invoices for {$classroom->name} (September to June).")
                            ->success()
                            ->send();
                    } else {
                        $student = Student::findOrFail($data['student_id']);
                        $count = $service->generateForStudent($student, $academicYear, $amount, $dueDay);
                        Notification::make()
                            ->title('Invoices Generated')
                            ->body("Successfully generated {$count} invoices for {$student->first_name} {$student->last_name} (September to June).")
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('generate_annual_package')
                ->label('Annual Package (فاتورة سنوية شاملة)')
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('student_id')
                        ->label('Student (التلميذ)')
                        ->options(fn () => Student::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->where('status', 'active')->get()->mapWithKeys(fn (Student $s) => [
                            $s->id => "{$s->first_name} {$s->last_name} (".($s->classroom?->name ?? 'No class').')',
                        ]))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('academic_year_id')
                        ->label('Academic Year (الموسم الدراسي)')
                        ->options(fn () => AcademicYear::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->pluck('name', 'id'))
                        ->default(fn () => AcademicYear::where('school_id', Filament::getTenant()?->id)
                            ->where('is_current', true)
                            ->value('id'))
                        ->required(),

                    Forms\Components\TextInput::make('monthly_tuition')
                        ->label('Monthly Tuition (واجب شهري × 10)')
                        ->numeric()
                        ->prefix('MAD')
                        ->default(fn () => FeeType::where('school_id', Filament::getTenant()?->id)
                            ->where('is_recurring_monthly', true)
                            ->value('default_amount') ?? 1500)
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('registration_fee')
                        ->label('Registration Fee (رسوم التسجيل)')
                        ->numeric()
                        ->prefix('MAD')
                        ->default(fn () => FeeType::where('school_id', Filament::getTenant()?->id)
                            ->where('name', 'like', '%Registration%')
                            ->value('default_amount') ?? 1000)
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('insurance_fee')
                        ->label('Insurance Fee (رسوم التأمين)')
                        ->numeric()
                        ->prefix('MAD')
                        ->default(fn () => FeeType::where('school_id', Filament::getTenant()?->id)
                            ->where('name', 'like', '%Insurance%')
                            ->value('default_amount') ?? 250)
                        ->required()
                        ->minValue(0),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Due Date (تاريخ الاستحقاق)')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data, InvoiceGenerationService $service): void {
                    $student = Student::findOrFail($data['student_id']);
                    $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                    $invoice = $service->generateAnnualPackage(
                        $student,
                        $academicYear,
                        (float) $data['monthly_tuition'],
                        (float) $data['registration_fee'],
                        (float) $data['insurance_fee'],
                        $data['due_date']
                    );

                    Notification::make()
                        ->title('Annual Package Invoice Created (تم إنشاء الفاتورة السنوية الشاملة)')
                        ->body("Invoice #{$invoice->invoice_number} created with total ".number_format($invoice->total_amount, 2).' MAD.')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
