<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance & Invoicing';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Information (بيانات الأداء)')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('student_id')
                                ->label('Student / التلميذ')
                                ->options(function () {
                                    $tenant = Filament::getTenant();

                                    return Student::where('school_id', $tenant?->id)
                                        ->get()
                                        ->mapWithKeys(fn (Student $s) => [
                                            $s->id => "{$s->first_name} {$s->last_name} ({$s->registration_number})".($s->classroom ? " - {$s->classroom->name}" : ''),
                                        ]);
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Forms\Components\Select $component, ?Payment $record) {
                                    if ($record && $record->invoice) {
                                        $component->state($record->invoice->student_id);
                                    }
                                })
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $student = Student::find($state);
                                        if ($student) {
                                            if ((float) $student->monthly_tuition_fee > 0) {
                                                $set('amount', (float) $student->monthly_tuition_fee);
                                            }

                                            $openInvoice = Invoice::where('student_id', $student->id)
                                                ->where('school_id', Filament::getTenant()?->id)
                                                ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
                                                ->latest()
                                                ->first();

                                            if ($openInvoice) {
                                                $set('invoice_id', $openInvoice->id);
                                                if ((float) $student->monthly_tuition_fee <= 0) {
                                                    $set('amount', (float) $openInvoice->remaining_amount);
                                                }
                                            }
                                        }
                                    }
                                }),

                            Forms\Components\Select::make('invoice_id')
                                ->label('Invoice / الفاتورة المرتبطة')
                                ->options(function (Forms\Get $get) {
                                    $studentId = $get('student_id');
                                    $tenant = Filament::getTenant();
                                    $query = Invoice::query();
                                    if ($tenant) {
                                        $query->where('school_id', $tenant->id);
                                    }
                                    if ($studentId) {
                                        $query->where('student_id', $studentId);
                                    }

                                    return $query->latest()->get()->mapWithKeys(fn (Invoice $inv) => [
                                        $inv->id => "{$inv->invoice_number} - {$inv->title} (Remaining: ".number_format($inv->remaining_amount, 2).' MAD)',
                                    ]);
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    if ($state && blank($get('amount'))) {
                                        $inv = Invoice::find($state);
                                        if ($inv) {
                                            $set('amount', (float) $inv->remaining_amount);
                                        }
                                    }
                                }),

                            Forms\Components\TextInput::make('amount')
                                ->label('Payment Amount / المبلغ المؤدى')
                                ->required()
                                ->numeric()
                                ->prefix('MAD')
                                ->minValue(0.01)
                                ->step(0.01),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Payment Date / تاريخ الأداء')
                                ->required()
                                ->default(now()),

                            Forms\Components\Select::make('payment_method')
                                ->label('Payment Method / طريقة الأداء')
                                ->options(PaymentMethod::class)
                                ->default(PaymentMethod::Cash->value)
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('receipt_number')
                                ->label('Receipt Number / رقم الوصل')
                                ->required()
                                ->default(fn () => 'REC-'.date('Ymd').'-'.strtoupper(Str::random(5)))
                                ->maxLength(255),

                            Forms\Components\TextInput::make('cheque_number')
                                ->label('Cheque Number / رقم الشيك')
                                ->maxLength(255)
                                ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', PaymentMethod::Cheque->value, PaymentMethod::Cheque], true))
                                ->required(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', PaymentMethod::Cheque->value, PaymentMethod::Cheque], true)),

                            Forms\Components\TextInput::make('bank_name')
                                ->label('Bank Name / اسم البنك')
                                ->maxLength(255)
                                ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', 'bank_transfer', PaymentMethod::Cheque->value, PaymentMethod::BankTransfer->value], true)),

                            Forms\Components\Textarea::make('notes')
                                ->label('Notes / ملاحظات')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt No.')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('invoice.student.first_name')
                    ->label('Student / التلميذ')
                    ->formatStateUsing(fn (Payment $record) => $record->invoice?->student ? "{$record->invoice->student->first_name} {$record->invoice->student->last_name}" : '-')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MAD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cheque_number')
                    ->label('Cheque No.')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('print_receipt')
                    ->label('Receipt (وصل)')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Payment $record): string => route('filament.admin.payment-receipt', [
                        'tenant' => Filament::getTenant(),
                        'payment' => $record,
                    ]))
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
