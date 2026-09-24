<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance & Absences (سجل الحضور والغياب)';

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function getDescription(): ?string
    {
        $student = $this->getOwnerRecord();
        if (! $student instanceof Student) {
            return null;
        }

        $total = $student->attendanceRecords()->count();
        $absent = $student->attendanceRecords()->where('status', AttendanceStatus::Absent)->count();
        $late = $student->attendanceRecords()->where('status', AttendanceStatus::Late)->count();
        $excused = $student->attendanceRecords()->where('status', AttendanceStatus::Excused)->count();
        $present = $student->attendanceRecords()->where('status', AttendanceStatus::Present)->count();

        $missedSessions = $absent + $excused;
        $missedHours = $missedSessions * 3.5;

        return "Summary: {$present} Present | {$absent} Absences | {$excused} Excused | {$late} Late — Total Missed Sessions: {$missedSessions} (~{$missedHours} hrs)";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
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

                    Forms\Components\Select::make('classroom_id')
                        ->label('Classroom')
                        ->options(fn () => Classroom::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->pluck('name', 'id'))
                        ->default(fn (RelationManager $livewire): ?int => $livewire->getOwnerRecord() instanceof Student ? $livewire->getOwnerRecord()->classroom_id : null)
                        ->required(),

                    Forms\Components\DatePicker::make('date')
                        ->label('Date')
                        ->default(now())
                        ->required(),

                    Forms\Components\Select::make('session')
                        ->label('Session')
                        ->options(AttendanceSession::class)
                        ->default(AttendanceSession::Morning->value)
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(AttendanceStatus::class)
                        ->default(AttendanceStatus::Absent->value)
                        ->required(),

                    Forms\Components\TextInput::make('remarks')
                        ->label('Remarks / Notes')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('session')
                    ->label('Session')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(35)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('From Date'),
                        Forms\Components\DatePicker::make('date_until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['date_until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('classroom_id')
                    ->label('Classroom')
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),

                Tables\Filters\SelectFilter::make('status')
                    ->options(AttendanceStatus::class),

                Tables\Filters\SelectFilter::make('session')
                    ->options(AttendanceSession::class),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['school_id'] = Filament::getTenant()?->id;

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
