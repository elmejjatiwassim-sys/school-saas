<?php

namespace App\Filament\Resources;

use App\Enums\AiActionLogStatus;
use App\Filament\Resources\AiActionLogResource\Pages;
use App\Models\AiActionLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiActionLogResource extends Resource
{
    protected static ?string $model = AiActionLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'AI & Automation';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'AI Audit Trail (سجل العمليات)';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('AI Action Log Details')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('action_type')
                                ->label('Action Type')
                                ->disabled(),

                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options(AiActionLogStatus::class)
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('confirmed_at')
                                ->label('Confirmed At')
                                ->disabled(),

                            Forms\Components\TextInput::make('user.name')
                                ->label('Triggered / Confirmed By')
                                ->disabled(),

                            Forms\Components\TextInput::make('ip_address')
                                ->label('IP Address')
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('created_at')
                                ->label('Proposed At')
                                ->disabled(),
                        ]),

                        Forms\Components\KeyValue::make('payload')
                            ->label('Action Parameters / Payload')
                            ->columnSpanFull()
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Admin User')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmed At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Pending / Not confirmed'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Proposed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AiActionLogStatus::class),

                Tables\Filters\SelectFilter::make('action_type')
                    ->options([
                        'register_student' => 'Register Student',
                        'quick_attendance' => 'Quick Attendance',
                        'generate_invoices' => 'Generate Invoices',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Read-only audit log: no bulk deletion
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
            'index' => Pages\ListAiActionLogs::route('/'),
        ];
    }
}
