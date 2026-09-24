<?php

namespace Database\Factories;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
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
            'date' => now()->toDateString(),
            'session' => fake()->randomElement(AttendanceSession::cases()),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
