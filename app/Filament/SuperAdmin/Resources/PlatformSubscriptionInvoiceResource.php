<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformSubscriptionInvoiceResource\Pages;
use App\Models\PlatformSubscriptionInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformSubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = PlatformSubscriptionInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Platform Invoices (فواتير المنصة)';

    protected static ?string $modelLabel = 'Platform Invoice (فاتورة اشتراك)';

    protected static ?string $pluralModelLabel = 'Platform Invoices (فواتير اشتراكات المنصة)';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('school_id')
                    ->relationship('school', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('billing_month')
                    ->label('Billing Month (YYYY-MM)')
                    ->required(),
                Forms\Components\TextInput::make('active_students_count')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('rate_applied')
                    ->numeric()
                    ->prefix('MAD')
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->prefix('MAD')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'unpaid' => 'Unpaid (غير مؤدى)',
                        'paid' => 'Paid (مؤدى)',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('stripe_invoice_id')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('school.name')
                    ->label('School Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('billing_month')
                    ->label('Billing Month')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_students_count')
                    ->label('Active Students')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate_applied')
                    ->label('Rate')
                    ->money('MAD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('MAD')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('stripe_invoice_id')
                    ->label('Stripe ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('school')
                    ->relationship('school', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformSubscriptionInvoices::route('/'),
        ];
    }
}
