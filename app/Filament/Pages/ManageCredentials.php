<?php

namespace App\Filament\Pages;

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\CredentialService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class ManageCredentials extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings & Users';

    protected static ?string $navigationLabel = 'Credentials (حسابات الدخول)';

    protected static ?string $title = 'User Credentials & Access (حسابات الدخول وكلمات المرور)';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.manage-credentials';

    public string $search = '';

    public string $roleFilter = 'all';

    public ?int $classroomId = null;

    // Password Reveal Modal state
    public bool $showPasswordModal = false;

    public ?string $modalName = null;

    public ?string $modalUsername = null;

    public ?string $modalPassword = null;

    public ?string $modalRole = null;

    // Bulk Generation Modal state
    public bool $showBulkModal = false;

    public ?int $bulkClassroomId = null;

    // Create Manual Account Modal
    public bool $showCreateModal = false;

    public string $createRole = 'teacher';

    public string $createName = '';

    public ?string $createEmail = null;

    public ?int $createClassroomId = null;

    public function mount(): void
    {
        $this->roleFilter = 'all';
    }

    public function getSchoolProperty(): ?School
    {
        return Filament::getTenant();
    }

    public function resetPassword(int $userId, CredentialService $service): void
    {
        $school = $this->school;
        abort_unless($school, 403);

        $user = User::where('school_id', $school->id)->findOrFail($userId);
        $newPassword = $service->resetPassword($user);

        $this->modalName = $user->name;
        $this->modalUsername = $user->username;
        $this->modalPassword = $newPassword;
        $this->modalRole = $user->role;
        $this->showPasswordModal = true;

        Notification::make()
            ->title('Password Reset (تمت إعادة تعيين كلمة المرور)')
            ->body("New password for {$user->name} generated successfully.")
            ->success()
            ->send();
    }

    public function generateAccountForStudent(int $studentId, CredentialService $service): void
    {
        $school = $this->school;
        abort_unless($school, 403);

        $student = Student::where('school_id', $school->id)->findOrFail($studentId);
        $user = $service->createStudentCredential($student);

        $this->modalName = $user->name;
        $this->modalUsername = $user->username;
        $this->modalPassword = $user->temporary_password;
        $this->modalRole = $user->role;
        $this->showPasswordModal = true;

        Notification::make()
            ->title('Account Created (تم إنشاء الحساب بنجاح)')
            ->body("Credentials created for student {$student->first_name} {$student->last_name}.")
            ->success()
            ->send();
    }

    public function toggleUserStatus(int $userId): void
    {
        $school = $this->school;
        abort_unless($school, 403);

        $user = User::where('school_id', $school->id)->findOrFail($userId);
        $user->is_active = ! $user->is_active;
        $user->save();

        Notification::make()
            ->title($user->is_active ? 'Account Activated (تم تفعيل الحساب)' : 'Account Deactivated (تم تعطيل الحساب)')
            ->status($user->is_active ? 'success' : 'warning')
            ->send();
    }

    public function openBulkModal(): void
    {
        $firstClass = Classroom::when($this->school, fn ($q) => $q->whereBelongsTo($this->school))->first();
        $this->bulkClassroomId = $firstClass?->id;
        $this->showBulkModal = true;
    }

    public function closeBulkModal(): void
    {
        $this->showBulkModal = false;
    }

    public function executeBulkGenerate(CredentialService $service): void
    {
        $school = $this->school;
        abort_unless($school, 403);

        if (! $this->bulkClassroomId) {
            Notification::make()->title('Please select a classroom')->warning()->send();

            return;
        }

        $classroom = Classroom::where('school_id', $school->id)->findOrFail($this->bulkClassroomId);
        $created = $service->bulkGenerateForClassroom($classroom);

        $this->showBulkModal = false;

        Notification::make()
            ->title('Bulk Generation Completed (اكتمل التوليد الجماعي)')
            ->body("Successfully generated {$created->count()} student accounts for {$classroom->name}.")
            ->success()
            ->send();
    }

    public function openCreateModal(): void
    {
        $this->createRole = 'teacher';
        $this->createName = '';
        $this->createEmail = null;
        $this->createClassroomId = null;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function executeCreateUser(CredentialService $service): void
    {
        $school = $this->school;
        abort_unless($school, 403);

        $this->validate([
            'createName' => 'required|string|max:255',
            'createRole' => 'required|string|in:teacher,admin,guardian',
            'createEmail' => 'nullable|email|max:255',
        ]);

        $username = $service->generateUniqueUsername($school);
        $tempPassword = $service->generateTemporaryPassword();

        $user = User::create([
            'name' => $this->createName,
            'username' => $username,
            'email' => $this->createEmail ?: null,
            'password' => Hash::make($tempPassword),
            'temporary_password' => $tempPassword,
            'school_id' => $school->id,
            'role' => $this->createRole,
            'classroom_id' => $this->createClassroomId,
            'is_active' => true,
        ]);

        $this->showCreateModal = false;

        $this->modalName = $user->name;
        $this->modalUsername = $user->username;
        $this->modalPassword = $tempPassword;
        $this->modalRole = $user->role;
        $this->showPasswordModal = true;

        Notification::make()
            ->title('User Created Successfully')
            ->body("Credentials generated for {$user->name}.")
            ->success()
            ->send();
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsersProperty(): Collection
    {
        $school = $this->school;
        if (! $school) {
            return new Collection;
        }

        $query = User::where('school_id', $school->id)
            ->with(['student.classroom.gradeLevel', 'classroom.gradeLevel']);

        if (! empty($this->search)) {
            $term = '%'.strtolower($this->search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        if ($this->roleFilter !== 'all' && $this->roleFilter !== 'unassigned_students') {
            $query->where('role', $this->roleFilter);
        }

        if ($this->classroomId) {
            $query->where(function ($q): void {
                $q->where('classroom_id', $this->classroomId)
                    ->orWhereHas('student', fn ($sq) => $sq->where('classroom_id', $this->classroomId));
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Student>
     */
    public function getUnassignedStudentsProperty(): Collection
    {
        $school = $this->school;
        if (! $school) {
            return new Collection;
        }

        $query = Student::where('school_id', $school->id)
            ->whereNull('user_id')
            ->with(['classroom.gradeLevel']);

        if (! empty($this->search)) {
            $term = '%'.strtolower($this->search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(massar_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(registration_number) LIKE ?', [$term]);
            });
        }

        if ($this->classroomId) {
            $query->where('classroom_id', $this->classroomId);
        }

        return $query->orderBy('first_name')->orderBy('last_name')->get();
    }

    /**
     * @return array<int, string>
     */
    public function getClassroomsProperty(): array
    {
        $school = $this->school;
        if (! $school) {
            return [];
        }

        return Classroom::where('school_id', $school->id)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array{total_users: int, students: int, teachers: int, unassigned: int}
     */
    public function getStatsProperty(): array
    {
        $school = $this->school;
        if (! $school) {
            return ['total_users' => 0, 'students' => 0, 'teachers' => 0, 'unassigned' => 0];
        }

        return [
            'total_users' => User::where('school_id', $school->id)->count(),
            'students' => User::where('school_id', $school->id)->where('role', 'student')->count(),
            'teachers' => User::where('school_id', $school->id)->where('role', 'teacher')->count(),
            'unassigned' => Student::where('school_id', $school->id)->whereNull('user_id')->count(),
        ];
    }
}
