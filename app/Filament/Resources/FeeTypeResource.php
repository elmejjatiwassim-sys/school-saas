<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeeTypeResource\Pages;
use App\Models\FeeType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeeTypeResource extends Resource
{
    protected static ?string $model = FeeType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Finance & Invoicing';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Fee Type Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Fee Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Monthly Tuition (واجب شهري)'),
                        Forms\Components\TextInput::make('default_amount')
                            ->label('Default Amount')
                            ->required()
                            ->numeric()
                            ->prefix('MAD')
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\Toggle::make('is_recurring_monthly')
                            ->label('Recurring Monthly (شهري متكرر)')
                            ->helperText('Enable if this fee recurs every month of the academic cycle')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Fee Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_amount')
                    ->label('Default Amount')
                    ->money('MAD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_recurring_monthly')
                    ->label('Monthly Recurring')
                    ->boolean()
                    ->sortable(),
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
                //
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeeTypes::route('/'),
            'create' => Pages\CreateFeeType::route('/create'),
            'edit' => Pages\EditFeeType::route('/{record}/edit'),
        ];
    }
}
