<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TakeAttendance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Attendance & Conduct';

    protected static ?string $navigationLabel = 'Take Attendance (تسجيل الغياب)';

    protected static ?string $title = 'Daily Classroom Attendance (تسجيل الغياب السريع)';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.take-attendance';

    public ?int $classroom_id = null;

    public ?string $date = null;

    public string $session = 'morning';

    /**
     * @var array<int, array{status: string, remarks: ?string}>
     */
    public array $studentsAttendance = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->session = AttendanceSession::Morning->value;

        $firstClassroom = Classroom::when(
            Filament::hasTenancy() && Filament::getTenant(),
            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
        )->first();

        if ($firstClassroom) {
            $this->classroom_id = $firstClassroom->id;
        }

        $this->loadStudents();
    }

    public function updatedClassroomId(): void
    {
        $this->loadStudents();
    }

    public function updatedDate(): void
    {
        $this->loadStudents();
    }

    public function updatedSession(): void
    {
        $this->loadStudents();
    }

    public function loadStudents(): void
    {
        $this->studentsAttendance = [];

        if (! $this->classroom_id) {
            return;
        }

        $students = Student::where('classroom_id', $this->classroom_id)
            ->where('status', 'active')
            ->when(
                Filament::hasTenancy() && Filament::getTenant(),
                fn ($q) => $q->whereBelongsTo(Filament::getTenant())
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $existingRecords = AttendanceRecord::whereIn('student_id', $students->pluck('id'))
            ->whereDate('date', $this->date)
            ->where('session', $this->session)
            ->get()
            ->keyBy('student_id');

        foreach ($students as $student) {
            if ($existingRecords->has($student->id)) {
                $record = $existingRecords->get($student->id);
                $this->studentsAttendance[$student->id] = [
                    'status' => $record->status->value,
                    'remarks' => $record->remarks,
                ];
            } else {
                $this->studentsAttendance[$student->id] = [
                    'status' => AttendanceStatus::Present->value,
                    'remarks' => '',
                ];
            }
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->studentsAttendance as $studentId => $data) {
            $this->studentsAttendance[$studentId]['status'] = AttendanceStatus::Present->value;
        }
    }

    public function markAllAbsent(): void
    {
        foreach ($this->studentsAttendance as $studentId => $data) {
            $this->studentsAttendance[$studentId]['status'] = AttendanceStatus::Absent->value;
        }
    }

    public function save(): void
    {
        if (! $this->classroom_id) {
            Notification::make()->title('Please select a classroom')->warning()->send();

            return;
        }

        $school = Filament::getTenant();
        $schoolId = $school ? $school->getKey() : null;

        $academicYearId = AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->value('id') ?? AcademicYear::where('school_id', $schoolId)->value('id');

        if (! $academicYearId) {
            Notification::make()->title('No academic year configured for this school')->danger()->send();

            return;
        }

        DB::transaction(function () use ($schoolId, $academicYearId): void {
            foreach ($this->studentsAttendance as $studentId => $data) {
                AttendanceRecord::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'student_id' => (int) $studentId,
                        'date' => $this->date,
                        'session' => $this->session,
                    ],
                    [
                        'classroom_id' => $this->classroom_id,
                        'academic_year_id' => $academicYearId,
                        'recorded_by_id' => auth()->id(),
                        'status' => $data['status'],
                        'remarks' => ! empty($data['remarks']) ? $data['remarks'] : null,
                    ]
                );
            }
        });

        Notification::make()
            ->title('Attendance Recorded (تم حفظ الغياب بنجاح)')
            ->body('Attendance has been successfully saved for '.count($this->studentsAttendance).' students.')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudentsProperty(): Collection
    {
        if (! $this->classroom_id) {
            return new Collection;
        }

        return Student::where('classroom_id', $this->classroom_id)
            ->where('status', 'active')
            ->when(
                Filament::hasTenancy() && Filament::getTenant(),
                fn ($q) => $q->whereBelongsTo(Filament::getTenant())
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getClassroomsProperty(): array
    {
        return Classroom::when(
            Filament::hasTenancy() && Filament::getTenant(),
            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
        )->pluck('name', 'id')->all();
    }
}
