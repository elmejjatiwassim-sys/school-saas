<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GuardianRelationshipType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(?School $school = null): void
    {
        $school ??= School::where('slug', 'al-amal')->first() ?? School::first();

        if (! $school) {
            return;
        }

        $academicYear = AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2026-2027'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'is_current' => true,
            ]
        );

        $gradeLevel = GradeLevel::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Grade 1'],
            ['code' => 'G1']
        );

        $classroom = Classroom::firstOrCreate(
            [
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'name' => 'Class 1-A',
            ],
            ['capacity' => 30]
        );

        $dummyData = [
            [
                'guardian' => [
                    'first_name' => 'Mohammed',
                    'last_name' => 'El Amrani',
                    'cin' => 'AB123456',
                    'phone' => '+212611223344',
                    'email' => 'm.elamrani@example.com',
                    'address' => '12 Rue de la Liberté, Casablanca',
                    'relationship_type' => GuardianRelationshipType::Father,
                ],
                'student' => [
                    'first_name' => 'Yassine',
                    'last_name' => 'El Amrani',
                    'date_of_birth' => '2018-03-15',
                    'gender' => Gender::Male,
                    'registration_number' => 'REG-2026-001',
                    'massar_code' => 'M130000001',
                    'status' => StudentStatus::Active,
                ],
            ],
            [
                'guardian' => [
                    'first_name' => 'Fatima',
                    'last_name' => 'Bennani',
                    'cin' => 'CD654321',
                    'phone' => '+212622334455',
                    'email' => 'f.bennani@example.com',
                    'address' => '45 Avenue Hassan II, Rabat',
                    'relationship_type' => GuardianRelationshipType::Mother,
                ],
                'student' => [
                    'first_name' => 'Aya',
                    'last_name' => 'Bennani',
                    'date_of_birth' => '2018-07-22',
                    'gender' => Gender::Female,
                    'registration_number' => 'REG-2026-002',
                    'massar_code' => 'M130000002',
                    'status' => StudentStatus::Active,
                ],
            ],
            [
                'guardian' => [
                    'first_name' => 'Omar',
                    'last_name' => 'Chraibi',
                    'cin' => 'EF789123',
                    'phone' => '+212633445566',
                    'email' => 'o.chraibi@example.com',
                    'address' => '8 Boulevard Mohammed V, Marrakech',
                    'relationship_type' => GuardianRelationshipType::Father,
                ],
                'student' => [
                    'first_name' => 'Adam',
                    'last_name' => 'Chraibi',
                    'date_of_birth' => '2018-11-05',
                    'gender' => Gender::Male,
                    'registration_number' => 'REG-2026-003',
                    'massar_code' => 'M130000003',
                    'status' => StudentStatus::Active,
                ],
            ],
            [
                'guardian' => [
                    'first_name' => 'Khadija',
                    'last_name' => 'Alaoui',
                    'cin' => 'GH456789',
                    'phone' => '+212644556677',
                    'email' => 'k.alaoui@example.com',
                    'address' => '23 Rue Tarik Ibn Ziad, Fes',
                    'relationship_type' => GuardianRelationshipType::Mother,
                ],
                'student' => [
                    'first_name' => 'Salma',
                    'last_name' => 'Alaoui',
                    'date_of_birth' => '2019-01-30',
                    'gender' => Gender::Female,
                    'registration_number' => 'REG-2026-004',
                    'massar_code' => 'M130000004',
                    'status' => StudentStatus::Active,
                ],
            ],
            [
                'guardian' => [
                    'first_name' => 'Hassan',
                    'last_name' => 'Idrissi',
                    'cin' => 'IJ987654',
                    'phone' => '+212655667788',
                    'email' => 'h.idrissi@example.com',
                    'address' => '67 Avenue des FAR, Tangier',
                    'relationship_type' => GuardianRelationshipType::LegalGuardian,
                ],
                'student' => [
                    'first_name' => 'Rayane',
                    'last_name' => 'Idrissi',
                    'date_of_birth' => '2018-09-12',
                    'gender' => Gender::Male,
                    'registration_number' => 'REG-2026-005',
                    'massar_code' => 'M130000005',
                    'status' => StudentStatus::Active,
                ],
            ],
        ];

        foreach ($dummyData as $item) {
            $guardian = Guardian::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'phone' => $item['guardian']['phone'],
                ],
                array_merge($item['guardian'], ['school_id' => $school->id])
            );

            Student::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'registration_number' => $item['student']['registration_number'],
                ],
                array_merge($item['student'], [
                    'school_id' => $school->id,
                    'guardian_id' => $guardian->id,
                    'classroom_id' => $classroom->id,
                ])
            );
        }
    }
}
