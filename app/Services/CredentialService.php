<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CredentialService
{
    /**
     * Generate a unique username according to the school code pattern: {$school->code}-XXXX
     */
    public function generateUniqueUsername(School $school): string
    {
        $prefix = $school->code ?: School::generateUniqueCode($school->name);

        do {
            $username = strtoupper($prefix).'-'.random_int(1000, 9999);
        } while (User::where('username', $username)->exists());

        return $username;
    }

    /**
     * Generate a readable and secure temporary password.
     */
    public function generateTemporaryPassword(int $length = 8): string
    {
        return Str::lower(Str::random(4)).random_int(1000, 9999);
    }

    /**
     * Create login credentials for a student.
     */
    public function createStudentCredential(Student $student, ?string $password = null): User
    {
        if ($student->user_id && $student->user) {
            return $student->user;
        }

        $school = $student->school;
        $username = $this->generateUniqueUsername($school);
        $tempPassword = $password ?? $this->generateTemporaryPassword();

        $user = User::create([
            'name' => "{$student->first_name} {$student->last_name}",
            'username' => $username,
            'email' => null,
            'password' => Hash::make($tempPassword),
            'temporary_password' => $tempPassword,
            'school_id' => $student->school_id,
            'role' => 'student',
            'student_id' => $student->id,
            'classroom_id' => $student->classroom_id,
            'is_active' => true,
        ]);

        $student->update(['user_id' => $user->id]);

        return $user;
    }

    /**
     * Reset a user's password with a fresh temporary password.
     */
    public function resetPassword(User $user, ?string $password = null): string
    {
        $newPassword = $password ?? $this->generateTemporaryPassword();

        $user->update([
            'password' => Hash::make($newPassword),
            'temporary_password' => $newPassword,
            'must_change_password' => true,
        ]);

        return $newPassword;
    }

    /**
     * Bulk generate credentials for all active students in a classroom who don't have accounts yet.
     *
     * @return Collection<int, array{student: Student, user: User, temporary_password: string}>
     */
    public function bulkGenerateForClassroom(Classroom $classroom): Collection
    {
        $students = Student::where('classroom_id', $classroom->id)
            ->whereNull('user_id')
            ->get();

        $created = collect();

        foreach ($students as $student) {
            $tempPassword = $this->generateTemporaryPassword();
            $user = $this->createStudentCredential($student, $tempPassword);

            $created->push([
                'student' => $student,
                'user' => $user,
                'temporary_password' => $tempPassword,
            ]);
        }

        return $created;
    }
}
