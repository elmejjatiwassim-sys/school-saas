<?php

namespace Database\Seeders;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::where('slug', 'al-amal')->first() ?? School::first();

        if (! $school) {
            return;
        }

        $academicYear = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
            ?? AcademicYear::where('school_id', $school->id)->first();

        if (! $academicYear) {
            return;
        }

        $students = Student::where('school_id', $school->id)->where('status', 'active')->get();

        if ($students->isEmpty()) {
            return;
        }

        // Seed 5 recent weekdays
        $days = [];
        $current = Carbon::now();
        while (count($days) < 5) {
            if (! $current->isWeekend()) {
                $days[] = $current->copy();
            }
            $current->subDay();
        }

        $sessions = [AttendanceSession::Morning, AttendanceSession::Afternoon];

        foreach ($days as $date) {
            foreach ($sessions as $session) {
                foreach ($students as $student) {
                    // 85% Present, 5% Absent, 5% Late, 5% Excused
                    $rand = rand(1, 100);
                    if ($rand <= 80) {
                        $status = AttendanceStatus::Present;
                        $remarks = null;
                    } elseif ($rand <= 88) {
                        $status = AttendanceStatus::Late;
                        $remarks = 'Arrived 15 minutes late due to transport delay';
                    } elseif ($rand <= 94) {
                        $status = AttendanceStatus::Absent;
                        $remarks = 'Unexcused absence';
                    } else {
                        $status = AttendanceStatus::Excused;
                        $remarks = 'Medical certificate provided';
                    }

                    AttendanceRecord::updateOrCreate(
                        [
                            'school_id' => $school->id,
                            'student_id' => $student->id,
                            'date' => $date->toDateString(),
                            'session' => $session->value,
                        ],
                        [
                            'classroom_id' => $student->classroom_id,
                            'academic_year_id' => $academicYear->id,
                            'status' => $status,
                            'remarks' => $remarks,
                        ]
                    );
                }
            }
        }
    }
}
