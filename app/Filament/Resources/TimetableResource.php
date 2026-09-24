<?php

namespace App\Filament\Resources;

use App\Enums\DayOfWeek;
use App\Filament\Resources\TimetableResource\Pages;
use App\Models\Timetable;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimetableResource extends Resource
{
    protected static ?string $model = Timetable::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Academic Management';

    protected static ?string $navigationLabel = 'Timetables (استعمالات الزمن)';

    protected static ?string $modelLabel = 'Timetable Slot (حصة دراسية)';

    protected static ?string $pluralModelLabel = 'Timetable Slots (جدول الحصص)';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Filament::hasTenancy() && Filament::getTenant()) {
            $query->whereBelongsTo(Filament::getTenant());
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Scheduling & Conflict Prevention')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('classroom_id')
                                ->label('Classroom / الفصل الدراسي')
                                ->relationship(
                                    name: 'classroom',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())
                                        : $query,
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->rules([
                                    fn (Forms\Get $get, ?Timetable $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $day = $get('day_of_week');
                                        $start = $get('start_time');
                                        $end = $get('end_time');
                                        if ($value && $day && $start && $end && $start < $end) {
                                            if (Timetable::hasClassroomConflict((int) $value, $day, $start, $end, $record?->id)) {
                                                $fail('This classroom already has a session scheduled during this time slot (هذا الفصل الدراسي لديه حصة أخرى في نفس هذا التوقيت).');
                                            }
                                        }
                                    },
                                ]),

                            Forms\Components\Select::make('day_of_week')
                                ->label('Day of Week / يوم الأسبوع')
                                ->options(DayOfWeek::class)
                                ->required()
                                ->live(),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start Time / وقت البدء')
                                ->seconds(false)
                                ->required()
                                ->live(),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('End Time / وقت الانتهاء')
                                ->seconds(false)
                                ->required()
                                ->rules([
                                    fn (Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $start = $get('start_time');
                                        if ($start && $value && $value <= $start) {
                                            $fail('End time must be after start time (وقت الانتهاء يجب أن يكون بعد وقت البدء).');
                                        }
                                    },
                                ])
                                ->live(),

                            Forms\Components\Select::make('subject_id')
                                ->label('Subject / المادة الدراسية')
                                ->relationship(
                                    name: 'subject',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())
                                        : $query,
                                )
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('teacher_id')
                                ->label('Teacher / الأستاذ')
                                ->relationship(
                                    name: 'teacher',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                                        ? $query->whereBelongsTo(Filament::getTenant())->whereIn('role', ['teacher', 'admin', 'staff'])
                                        : $query,
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->rules([
                                    fn (Forms\Get $get, ?Timetable $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $day = $get('day_of_week');
                                        $start = $get('start_time');
                                        $end = $get('end_time');
                                        if ($value && $day && $start && $end && $start < $end) {
                                            if (Timetable::hasTeacherConflict((int) $value, $day, $start, $end, $record?->id)) {
                                                $fail('This teacher is already scheduled for another class during this time slot (هذا الأستاذ مبرمج لحصة أخرى في نفس هذا التوقيت).');
                                            }
                                        }
                                    },
                                ]),

                            Forms\Components\TextInput::make('room_name')
                                ->label('Room / القاعة')
                                ->placeholder('e.g. Salle 101, Lab 2')
                                ->maxLength(255)
                                ->nullable()
                                ->rules([
                                    fn (Forms\Get $get, ?Timetable $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $day = $get('day_of_week');
                                        $start = $get('start_time');
                                        $end = $get('end_time');
                                        if ($value && $day && $start && $end && $start < $end) {
                                            if (Timetable::hasRoomConflict((string) $value, $day, $start, $end, $record?->id)) {
                                                $fail('This room is already reserved during this time slot (هذه القاعة محجوزة مسبقًا في نفس هذا التوقيت).');
                                            }
                                        }
                                    },
                                ]),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('time_slot')
                    ->label('Time Slot')
                    ->state(fn (Timetable $record): string => substr($record->start_time, 0, 5).' - '.substr($record->end_time, 0, 5))
                    ->badge()
                    ->color('gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('start_time', $direction)),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Subject')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Teacher')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('room_name')
                    ->label('Room')
                    ->badge()
                    ->color('warning')
                    ->default('—')
                    ->sortable(),
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

                Tables\Filters\SelectFilter::make('teacher_id')
                    ->label('Teacher')
                    ->relationship(
                        name: 'teacher',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())->whereIn('role', ['teacher', 'admin'])
                            : $query,
                    ),

                Tables\Filters\SelectFilter::make('day_of_week')
                    ->label('Day of Week')
                    ->options(DayOfWeek::class),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimetables::route('/'),
            'create' => Pages\CreateTimetable::route('/create'),
            'edit' => Pages\EditTimetable::route('/{record}/edit'),
        ];
    }
}
