<?php

namespace App\Filament\Resources;

use App\Enums\GuardianRelationshipType;
use App\Filament\Resources\GuardianResource\Pages;
use App\Filament\Resources\GuardianResource\RelationManagers;
use App\Models\Guardian;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuardianResource extends Resource
{
    protected static ?string $model = Guardian::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Students & Parents';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Guardian Information')
                    ->schema([
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
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->state(fn (Guardian $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cin')
                    ->label('CIN')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('relationship_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('students_count')
                    ->counts('students')
                    ->badge()
                    ->color('info')
                    ->label('Students')
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
            RelationManagers\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuardians::route('/'),
            'create' => Pages\CreateGuardian::route('/create'),
            'edit' => Pages\EditGuardian::route('/{record}/edit'),
        ];
    }
}
