<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timetable>
 */
class TimetableFactory extends Factory
{
    protected $model = Timetable::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'classroom_id' => fn (array $attributes) => Classroom::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'subject_id' => fn (array $attributes) => Subject::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'teacher_id' => fn (array $attributes) => User::factory()->create([
                'school_id' => $attributes['school_id'],
                'role' => 'teacher',
            ])->id,
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Room '.fake()->numberBetween(1, 20),
        ];
    }
}
