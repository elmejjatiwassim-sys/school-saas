<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Models\Classroom;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_year_id')
                    ->relationship(
                        name: 'academicYear',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('grade_level_id')
                    ->relationship(
                        name: 'gradeLevel',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Class 1-A'),
                Forms\Components\TextInput::make('capacity')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('e.g. 30'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Academic Year')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gradeLevel.name')
                    ->label('Grade Level')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
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
                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->label('Academic Year')
                    ->relationship(
                        name: 'academicYear',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),
                Tables\Filters\SelectFilter::make('grade_level_id')
                    ->label('Grade Level')
                    ->relationship(
                        name: 'gradeLevel',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),
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
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
