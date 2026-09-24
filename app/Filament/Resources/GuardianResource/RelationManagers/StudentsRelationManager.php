<?php

namespace App\Filament\Resources\GuardianResource\RelationManagers;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $recordTitleAttribute = 'first_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('classroom_id')
                    ->label('Classroom')
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    )
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record) => "{$record->name} (".($record->gradeLevel?->name ?? 'Grade').')')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->required()
                    ->maxDate(now()),
                Forms\Components\Select::make('gender')
                    ->options(Gender::class)
                    ->required(),
                Forms\Components\TextInput::make('registration_number')
                    ->required()
                    ->default(fn () => 'REG-'.strtoupper(Str::random(6)))
                    ->maxLength(255),
                Forms\Components\TextInput::make('massar_code')
                    ->label('Massar Code')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options(StudentStatus::class)
                    ->default(StudentStatus::Active->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Student Name')
                    ->state(fn (Student $record): string => "{$record->first_name} {$record->last_name}"),
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Reg. No')
                    ->searchable(),
                Tables\Columns\TextColumn::make('massar_code')
                    ->label('Massar Code')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom'),
                Tables\Columns\TextColumn::make('gender')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (Filament::hasTenancy() && Filament::getTenant()) {
                            $data['school_id'] = Filament::getTenant()->getKey();
                        }

                        return $data;
                    }),
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
}
