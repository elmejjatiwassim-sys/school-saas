<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('MAD')
                    ->minValue(0.01)
                    ->step(0.01)
                    ->default(fn (RelationManager $livewire): float => (float) ($livewire->getOwnerRecord() instanceof Invoice ? $livewire->getOwnerRecord()->remaining_amount : 0)),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('payment_method')
                    ->label('Payment Method')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('cheque_number')
                    ->label('Cheque Number / رقم الشيك')
                    ->maxLength(255)
                    ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', PaymentMethod::Cheque->value, PaymentMethod::Cheque], true))
                    ->required(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', PaymentMethod::Cheque->value, PaymentMethod::Cheque], true)),
                Forms\Components\TextInput::make('bank_name')
                    ->label('Bank Name / اسم البنك')
                    ->maxLength(255)
                    ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cheque', 'bank_transfer', PaymentMethod::Cheque->value, PaymentMethod::BankTransfer->value], true)),
                Forms\Components\TextInput::make('receipt_number')
                    ->label('Receipt Number')
                    ->required()
                    ->default(fn () => 'REC-'.date('Ymd').'-'.strtoupper(Str::random(5)))
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('receipt_number')
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt No.')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
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
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Record Payment (تسجيل أداء)')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (Filament::hasTenancy() && Filament::getTenant()) {
                            $data['school_id'] = Filament::getTenant()->getKey();
                        }

                        return $data;
                    }),
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
}
