<?php

namespace App\Filament\Resources;

use App\Enums\AcademicMonth;
use App\Enums\FeeInvoiceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Finance & Invoicing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->relationship(
                                    name: 'academicYear',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())
                                        : $query,
                                )
                                ->default(fn () => AcademicYear::where('school_id', Filament::getTenant()?->id)
                                    ->where('is_current', true)
                                    ->value('id'))
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('student_id')
                                ->label('Student')
                                ->relationship(
                                    name: 'student',
                                    titleAttribute: 'first_name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())
                                        : $query,
                                )
                                ->getOptionLabelFromRecordUsing(fn (Student $record) => "{$record->first_name} {$record->last_name} (".($record->classroom?->name ?? 'No classroom').')')
                                ->searchable(['first_name', 'last_name', 'registration_number', 'massar_code'])
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('type')
                                ->label('Invoice Type (نوع الفاتورة)')
                                ->options(InvoiceType::class)
                                ->default(InvoiceType::Monthly->value)
                                ->live()
                                ->required(),

                            Forms\Components\Select::make('invoice_type')
                                ->label('Fee Category / تصنيف الرسم')
                                ->options(FeeInvoiceType::class)
                                ->default(FeeInvoiceType::MonthlyFee->value)
                                ->required(),

                            Forms\Components\Select::make('billing_month')
                                ->label('Billing Month (Academic Cycle)')
                                ->options(collect(AcademicMonth::academicCycleMonths())->mapWithKeys(fn (AcademicMonth $m) => [
                                    $m->value => $m->getLabel(),
                                ]))
                                ->visible(fn (Forms\Get $get): bool => $get('type') === InvoiceType::Monthly->value || $get('type') === 'monthly' || blank($get('type')))
                                ->required(fn (Forms\Get $get): bool => $get('type') === InvoiceType::Monthly->value || $get('type') === 'monthly' || blank($get('type')))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?int $state) {
                                    if (! $state) {
                                        return;
                                    }

                                    $monthEnum = AcademicMonth::tryFrom($state);
                                    if (! $monthEnum) {
                                        return;
                                    }

                                    $academicYear = AcademicYear::find($get('academic_year_id'));
                                    $startYear = $academicYear && $academicYear->start_date
                                        ? Carbon::parse($academicYear->start_date)->year
                                        : (int) date('Y');
                                    $endYear = $academicYear && $academicYear->end_date
                                        ? Carbon::parse($academicYear->end_date)->year
                                        : ($startYear + 1);

                                    $year = in_array($state, [9, 10, 11, 12], true) ? $startYear : $endYear;

                                    $set('billing_year', $year);
                                    $set('title', 'واجب شهر '.$monthEnum->getArabicName().' '.$year);

                                    $monthPadded = str_pad((string) $state, 2, '0', STR_PAD_LEFT);
                                    $set('due_date', "{$year}-{$monthPadded}-05");
                                }),

                            Forms\Components\TextInput::make('billing_year')
                                ->label('Billing Year')
                                ->numeric()
                                ->required()
                                ->default(now()->year),

                            Forms\Components\TextInput::make('title')
                                ->label('Invoice Title')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. واجب شهر شتنبر 2026'),

                            Forms\Components\TextInput::make('invoice_number')
                                ->label('Invoice Number')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->default(fn () => 'INV-'.date('Ym').'-'.strtoupper(Str::random(5)))
                                ->maxLength(255),

                            Forms\Components\TextInput::make('total_amount')
                                ->label('Total Amount')
                                ->required()
                                ->numeric()
                                ->prefix('MAD')
                                ->minValue(0)
                                ->step(0.01)
                                ->default(1500),

                            Forms\Components\TextInput::make('paid_amount')
                                ->label('Paid Amount')
                                ->numeric()
                                ->prefix('MAD')
                                ->default(0)
                                ->disabled()
                                ->helperText('Automatically updated when payments are recorded'),

                            Forms\Components\DatePicker::make('due_date')
                                ->label('Due Date')
                                ->required()
                                ->default(now()->addDays(15)),

                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options(InvoiceStatus::class)
                                ->default(InvoiceStatus::Unpaid->value)
                                ->required(),
                        ]),
                    ]),

                Forms\Components\Section::make('Invoice Line Items (بنود الفاتورة)')
                    ->description('Detailed items breakdown for annual package and multi-item invoices.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Description / البيان')
                                    ->required()
                                    ->columnSpan(['md' => 4]),

                                Forms\Components\Select::make('fee_type_id')
                                    ->label('Fee Type / نوع الرسوم')
                                    ->options(fn () => FeeType::when(
                                        Filament::hasTenancy() && Filament::getTenant(),
                                        fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                                    )->pluck('name', 'id'))
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, ?int $state) {
                                        if ($state && $feeType = FeeType::find($state)) {
                                            $set('description', $feeType->name);
                                            $set('amount', $feeType->default_amount);
                                        }
                                    })
                                    ->columnSpan(['md' => 3]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty / الكمية')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => static::updateTotalFromRepeater($set, $get))
                                    ->columnSpan(['md' => 2]),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Unit Price / المبلغ')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => static::updateTotalFromRepeater($set, $get))
                                    ->columnSpan(['md' => 3]),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->addActionLabel('+ Add Item / إضافة بند')
                            ->reorderable()
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function updateTotalFromRepeater(Forms\Set $set, Forms\Get $get): void
    {
        $items = $get('items') ?? [];
        $total = 0;
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $amount = (float) ($item['amount'] ?? 0);
            $total += ($qty * $amount);
        }
        if ($total > 0) {
            $set('total_amount', round($total, 2));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_type')
                    ->label('Category')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->state(fn (Invoice $record): string => $record->student ? "{$record->student->first_name} {$record->student->last_name}" : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.classroom.name')
                    ->label('Classroom')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title / Month')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MAD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('MAD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->state(fn (Invoice $record): float => $record->remaining_amount)
                    ->money('MAD')
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Invoice Type')
                    ->options(InvoiceType::class),

                Tables\Filters\SelectFilter::make('invoice_type')
                    ->label('Fee Category')
                    ->options(FeeInvoiceType::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class),

                Tables\Filters\SelectFilter::make('billing_month')
                    ->label('Billing Month')
                    ->options(collect(AcademicMonth::academicCycleMonths())->mapWithKeys(fn (AcademicMonth $m) => [
                        $m->value => $m->getLabel(),
                    ])),

                Tables\Filters\SelectFilter::make('classroom')
                    ->label('Classroom')
                    ->relationship(
                        name: 'student.classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('online_payment_link')
                    ->label('رابط الأداء الإلكتروني / Online Payment Link')
                    ->icon('heroicon-o-credit-card')
                    ->color('warning')
                    ->modalHeading('بوابة الأداء الإلكتروني (قريباً / Coming Soon)')
                    ->modalDescription('ميزة الأداء الإلكتروني المباشر بالبطاقة البنكية وتتبع الدفع الفوري ستتوفر في التحديث القادم! يمكنك حالياً تسجيل الأداءات يدوياً عبر الوصولات النقدية والتحويلات.')
                    ->modalIcon('heroicon-o-sparkles')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق / Close')
                    ->form([
                        Forms\Components\TextInput::make('future_public_link')
                            ->label('الرابط المخصص للفاتورة / Public Payment Link (Coming Soon)')
                            ->default(fn (Invoice $record): string => rtrim(config('app.url'), '/').'/pay/invoice/'.($record->payment_token ?? 'preview'))
                            ->disabled()
                            ->prefix('🔗')
                            ->suffix('قريباً / Coming Soon')
                            ->helperText('يمكن لأولياء الأمور مستقبلاً أداء الواجبات إلكترونياً عبر هذا الرابط فور تفعيل بوابة الدفع.')
                            ->columnSpanFull(),
                    ]),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->visible(fn (Invoice $record): bool => $record->payments()->exists())
                    ->url(fn (Invoice $record): ?string => $record->payments()->latest('payment_date')->first()
                        ? route('filament.admin.payment-receipt', [
                            'tenant' => Filament::getTenant(),
                            'payment' => $record->payments()->latest('payment_date')->first(),
                        ])
                        : null)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
