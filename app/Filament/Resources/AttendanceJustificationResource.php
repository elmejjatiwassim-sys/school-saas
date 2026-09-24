<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceStatus;
use App\Enums\JustificationStatus;
use App\Filament\Resources\AttendanceJustificationResource\Pages;
use App\Models\Attendance;
use App\Services\SessionAttendanceService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceJustificationResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Attendance & Conduct';

    protected static ?string $navigationLabel = 'Absence Justifications (تبريرات الغياب)';

    protected static ?string $modelLabel = 'Absence Justification (تبرير غياب)';

    protected static ?string $pluralModelLabel = 'Absence Justifications (تبريرات الغياب)';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->isSupervisor();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->when(
                Filament::hasTenancy() && Filament::getTenant(),
                fn ($q) => $q->whereBelongsTo(Filament::getTenant())
            )
            ->where(function (Builder $q) {
                $q->whereNotNull('guardian_justification_note')
                    ->orWhereIn('status', [AttendanceStatus::Absent, AttendanceStatus::Late, AttendanceStatus::Excused])
                    ->orWhereIn('justification_status', [JustificationStatus::Pending, JustificationStatus::Approved, JustificationStatus::Rejected]);
            });

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Justification Details')
                    ->schema([
                        Forms\Components\TextInput::make('student_name')
                            ->label('Student / التلميذ')
                            ->formatStateUsing(fn ($record) => $record?->student?->fullName)
                            ->disabled(),

                        Forms\Components\DatePicker::make('date')
                            ->label('Date / التاريخ')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Attendance Status / حالة الحضور')
                            ->options(AttendanceStatus::class)
                            ->disabled(),

                        Forms\Components\Select::make('justification_status')
                            ->label('Justification Status / حالة التبرير')
                            ->options(JustificationStatus::class)
                            ->disabled(),

                        Forms\Components\Textarea::make('guardian_justification_note')
                            ->label('Guardian Reason / تبرير ولي الأمر')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('late_arrival_time')
                            ->label('Late Arrival Time / وقت الوصول')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date / التاريخ')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student / التلميذ')
                    ->state(fn (Attendance $record): string => $record->student ? "{$record->student->first_name} {$record->student->last_name}" : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom / الفصل')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('timetable.subject.name')
                    ->label('Subject / المادة')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('timetable.start_time')
                    ->label('Time / التوقيت')
                    ->formatStateUsing(fn ($record) => $record->timetable ? substr((string) $record->timetable->start_time, 0, 5).' - '.substr((string) $record->timetable->end_time, 0, 5) : '—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status / الحالة')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('justification_status')
                    ->label('Justification / التبرير')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('guardian_justification_note')
                    ->label('Reason / سبب الغياب')
                    ->limit(35)
                    ->tooltip(fn (Attendance $record) => $record->guardian_justification_note)
                    ->searchable(),

                Tables\Columns\TextColumn::make('guardian_justification_attachment')
                    ->label('Attachment / المرفق')
                    ->formatStateUsing(fn ($state) => filled($state) ? '📎 View / معاينة' : '—')
                    ->url(fn (Attendance $record) => filled($record->guardian_justification_attachment)
                        ? (str_starts_with((string) $record->guardian_justification_attachment, 'http')
                            ? $record->guardian_justification_attachment
                            : asset('storage/'.$record->guardian_justification_attachment))
                        : null, shouldOpenInNewTab: true),

                Tables\Columns\TextColumn::make('late_arrival_time')
                    ->label('Arrival / وقت الوصول')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By / المراجع')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('justification_status')
                    ->label('Justification Status')
                    ->options(JustificationStatus::class),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Attendance Status')
                    ->options(AttendanceStatus::class),

                Tables\Filters\SelectFilter::make('classroom_id')
                    ->label('Classroom')
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => Filament::hasTenancy() && Filament::getTenant()
                            ? $query->whereBelongsTo(Filament::getTenant())
                            : $query,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('قبول التبرير / Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Attendance $record): bool => (auth()->user()?->isSupervisor() || auth()->user()?->isAdmin())
                        && $record->justification_status !== JustificationStatus::Approved
                    )
                    ->requiresConfirmation()
                    ->modalHeading('قبول تبرير الغياب / Approve Justification')
                    ->modalDescription('هل أنت متأكد من قبول تبرير غياب هذا التلميذ؟ سيتم تحويل الحالة إلى مبرر (Excused).')
                    ->action(function (Attendance $record, SessionAttendanceService $service): void {
                        $service->approveJustification($record, auth()->user());
                        Notification::make()
                            ->title('تم قبول التبرير بنجاح / Justification Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_late')
                    ->label('تعديل إلى متأخر / Mark Late')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (Attendance $record): bool => auth()->user()?->isSupervisor() || auth()->user()?->isAdmin()
                    )
                    ->form([
                        Forms\Components\TimePicker::make('late_arrival_time')
                            ->label('وقت الوصول / Arrival Timestamp')
                            ->default(now()->format('H:i'))
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(function (Attendance $record, array $data, SessionAttendanceService $service): void {
                        $service->markAsLate($record, auth()->user(), $data['late_arrival_time'] ?? null);
                        Notification::make()
                            ->title('تم التعديل إلى متأخر بنجاح / Marked as Late')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رفض التبرير / Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Attendance $record): bool => (auth()->user()?->isSupervisor() || auth()->user()?->isAdmin())
                        && $record->justification_status !== JustificationStatus::Rejected
                    )
                    ->requiresConfirmation()
                    ->modalHeading('رفض تبرير الغياب / Reject Justification')
                    ->modalDescription('هل أنت متأكد من رفض تبرير الغياب؟')
                    ->action(function (Attendance $record, SessionAttendanceService $service): void {
                        $service->rejectJustification($record, auth()->user());
                        Notification::make()
                            ->title('تم رفض التبرير / Justification Rejected')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceJustifications::route('/'),
        ];
    }
}
