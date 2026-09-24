<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Enums\JustificationStatus;
use App\Filament\Resources\AttendanceRecordResource\Pages;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Attendance & Conduct';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Attendance Record Details')
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
                                ->live()
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('student_id')
                                ->label('Student')
                                ->options(function (Forms\Get $get) {
                                    $classroomId = $get('classroom_id');
                                    $query = Student::when(
                                        Filament::hasTenancy() && Filament::getTenant(),
                                        fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                                    );

                                    if ($classroomId) {
                                        $query->where('classroom_id', $classroomId);
                                    }

                                    return $query->get()->mapWithKeys(fn (Student $s) => [
                                        $s->id => "{$s->first_name} {$s->last_name} ({$s->registration_number})",
                                    ]);
                                })
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('timetable_id')
                                ->label('Timetable Session / الحصة')
                                ->relationship(
                                    name: 'timetable',
                                    titleAttribute: 'room_name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())->with(['subject', 'teacher'])
                                        : $query->with(['subject', 'teacher']),
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->subject?->name} ({$record->day_of_week?->value} {$record->start_time}-{$record->end_time}) - {$record->teacher?->name}")
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            Forms\Components\DatePicker::make('date')
                                ->label('Date')
                                ->default(now())
                                ->required(),

                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options(AttendanceStatus::class)
                                ->default(AttendanceStatus::Present->value)
                                ->live()
                                ->required(),

                            Forms\Components\TimePicker::make('late_arrival_time')
                                ->label('Late Arrival Time / وقت الوصول')
                                ->visible(fn (Forms\Get $get) => $get('status') === AttendanceStatus::Late->value || $get('status') === 'late')
                                ->nullable(),

                            Forms\Components\Select::make('justification_status')
                                ->label('Justification Status / حالة التبرير')
                                ->options(JustificationStatus::class)
                                ->default(JustificationStatus::Pending->value)
                                ->nullable(),

                            Forms\Components\Textarea::make('guardian_justification_note')
                                ->label('Guardian Justification / تبرير ولي الأمر')
                                ->rows(2)
                                ->nullable()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('remarks')
                                ->label('Remarks / Notes')
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->state(fn (AttendanceRecord $record): string => $record->student ? "{$record->student->first_name} {$record->student->last_name}" : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('timetable.subject.name')
                    ->label('Subject / Slot')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_arrival_time')
                    ->label('Late Time')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('justification_status')
                    ->label('Justification')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classroom_id')
                    ->label('Classroom')
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),

                Tables\Filters\SelectFilter::make('session')
                    ->options(AttendanceSession::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(AttendanceStatus::class),

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
            'index' => Pages\ListAttendanceRecords::route('/'),
            'create' => Pages\CreateAttendanceRecord::route('/create'),
            'edit' => Pages\EditAttendanceRecord::route('/{record}/edit'),
        ];
    }
}
