<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\JustificationStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => fn (array $attributes) => Student::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'classroom_id' => fn (array $attributes) => Classroom::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'timetable_id' => fn (array $attributes) => Timetable::factory()->create([
                'school_id' => $attributes['school_id'],
                'classroom_id' => $attributes['classroom_id'],
            ])->id,
            'recorded_by_id' => fn (array $attributes) => User::factory()->create([
                'school_id' => $attributes['school_id'],
                'role' => 'teacher',
            ])->id,
            'date' => now()->toDateString(),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'late_arrival_time' => null,
            'remarks' => fake()->optional()->sentence(),
            'guardian_justification_note' => null,
            'guardian_justification_attachment' => null,
            'justification_status' => JustificationStatus::Pending,
            'reviewed_by_id' => null,
        ];
    }
}
