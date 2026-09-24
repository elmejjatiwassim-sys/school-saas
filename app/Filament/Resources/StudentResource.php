<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\GuardianRelationshipType;
use App\Enums\StudentStatus;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\CredentialService;
use App\Services\InvoiceGenerationService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Students & Parents';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Student Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Personal Info')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('first_name')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('last_name')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\DatePicker::make('date_of_birth')
                                        ->maxDate(now()),
                                    Forms\Components\Select::make('gender')
                                        ->options(Gender::class)
                                        ->required(),
                                    Forms\Components\TextInput::make('registration_number')
                                        ->label('Registration Number')
                                        ->disabled()
                                        ->placeholder('Auto-generated on save'),
                                    Forms\Components\TextInput::make('massar_code')
                                        ->label('Massar Code')
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255)
                                        ->placeholder('e.g. G123456789'),
                                    Forms\Components\Select::make('status')
                                        ->options(StudentStatus::class)
                                        ->default(StudentStatus::Active->value)
                                        ->required(),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Academic Assignment')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\Select::make('classroom_id')
                                    ->label('Classroom')
                                    ->relationship(
                                        name: 'classroom',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query) {
                                            if (Filament::hasTenancy() && Filament::getTenant()) {
                                                $tenant = Filament::getTenant();
                                                $query->whereBelongsTo($tenant);

                                                $currentYearId = AcademicYear::where('school_id', $tenant->id)
                                                    ->where('is_current', true)
                                                    ->value('id');

                                                if ($currentYearId) {
                                                    $query->where('academic_year_id', $currentYearId);
                                                }
                                            }
                                        },
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Classroom $record) => "{$record->name} (".($record->gradeLevel?->name ?? 'Grade').($record->academicYear ? ' - '.$record->academicYear->name : '').')')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Guardian Info')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Forms\Components\Select::make('guardian_id')
                                    ->label('Guardian')
                                    ->relationship(
                                        name: 'guardian',
                                        titleAttribute: 'first_name',
                                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                            ? $query->whereBelongsTo(Filament::getTenant())
                                            : $query,
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Guardian $record) => "{$record->first_name} {$record->last_name} ({$record->phone})".($record->cin ? " - CIN: {$record->cin}" : ''))
                                    ->searchable(['first_name', 'last_name', 'phone', 'cin'])
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('first_name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('last_name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\Select::make('relationship_type')
                                                ->options(GuardianRelationshipType::class)
                                                ->required(),
                                            Forms\Components\TextInput::make('phone')
                                                ->tel()
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('email')
                                                ->email()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('cin')
                                                ->label('CIN')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('address')
                                                ->columnSpanFull()
                                                ->maxLength(255),
                                        ]),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        if (Filament::hasTenancy() && Filament::getTenant()) {
                                            $data['school_id'] = Filament::getTenant()->getKey();
                                        }

                                        return Guardian::create($data)->getKey();
                                    }),
                            ]),

                        Forms\Components\Tabs\Tab::make('Financial (البيانات المالية)')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('monthly_tuition_fee')
                                    ->label('Monthly Tuition Fee / واجب التمدرس الشهري')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->default(0.00)
                                    ->helperText('الواجب الشهري المخصص لهذا التلميذ ليتم احتسابه واقتراحه تلقائياً عند استخلاص الأداء.'),
                                Forms\Components\TextInput::make('opening_balance')
                                    ->label('Opening Balance / الرصيد الافتتاحي (متأخرات سابقة)')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->default(0.00)
                                    ->helperText('إذا كان لدى التلميذ متأخرات سابقة عند التحاقه بالمنصة، سيتم إنشاء فاتورة رصيد افتتاحي معلقة تلقائيًا بهذا المبلغ.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->state(fn (Student $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Reg. No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('massar_code')
                    ->label('Massar Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('guardian.phone')
                    ->label('Guardian Phone')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Username')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->default('No Account')
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Opening Balance')
                    ->money('MAD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classroom_id')
                    ->label('Classroom')
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Gender')
                    ->options(Gender::class),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(StudentStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('generate_invoices')
                    ->label('10-Month Invoices')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->form([
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
                    ->action(function (Student $record, array $data, InvoiceGenerationService $service): void {
                        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                        $count = $service->generateForStudent($record, $academicYear, (float) $data['monthly_amount'], (int) $data['due_day']);

                        Notification::make()
                            ->title('Invoices Generated')
                            ->body("Generated {$count} invoices for {$record->first_name} {$record->last_name} (September to June).")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('generate_annual_package')
                    ->label('Annual Package (فاتورة سنوية شاملة)')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->form([
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
                    ->action(function (Student $record, array $data, InvoiceGenerationService $service): void {
                        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                        $invoice = $service->generateAnnualPackage(
                            $record,
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
                Tables\Actions\Action::make('generate_credentials')
                    ->label('Generate Account (حساب دخول)')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (Student $record): bool => blank($record->user_id))
                    ->action(function (Student $record, CredentialService $service): void {
                        $user = $service->createStudentCredential($record);
                        Notification::make()
                            ->title('Credentials Created (تم إنشاء الحساب)')
                            ->body("Username: {$user->username} | Temporary Password: {$user->temporary_password}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password (إعادة تعيين)')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Student $record): bool => ! blank($record->user_id))
                    ->requiresConfirmation()
                    ->action(function (Student $record, CredentialService $service): void {
                        if (! $record->user) {
                            return;
                        }
                        $newPass = $service->resetPassword($record->user);
                        Notification::make()
                            ->title('Password Reset (تمت إعادة تعيين كلمة المرور)')
                            ->body("Username: {$record->user->username} | New Password: {$newPass}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_generate_credentials')
                        ->label('Generate Credentials (توليد حسابات الدخول)')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->action(function (Collection $records, CredentialService $service): void {
                            $count = 0;
                            foreach ($records as $student) {
                                if (blank($student->user_id)) {
                                    $service->createStudentCredential($student);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('Credentials Generated')
                                ->body("Generated login credentials for {$count} students.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_generate_invoices')
                        ->label('Generate 10-Month Invoices (توليد فواتير 10 أشهر)')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('success')
                        ->form([
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
                        ->action(function (Collection $records, array $data, InvoiceGenerationService $service): void {
                            $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                            $totalCount = 0;
                            foreach ($records as $student) {
                                $totalCount += $service->generateForStudent($student, $academicYear, (float) $data['monthly_amount'], (int) $data['due_day']);
                            }

                            Notification::make()
                                ->title('Bulk Invoices Generated')
                                ->body("Generated {$totalCount} invoices across {$records->count()} students.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StudentResource\RelationManagers\AttendanceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
