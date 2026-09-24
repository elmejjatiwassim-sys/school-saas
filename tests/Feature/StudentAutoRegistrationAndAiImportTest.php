<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AiStudentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentAutoRegistrationAndAiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_generate_registration_number_scoped_per_school(): void
    {
        $year = date('Y');

        $schoolA = School::factory()->create();
        $classroomA = Classroom::factory()->create(['school_id' => $schoolA->id]);

        $schoolB = School::factory()->create();
        $classroomB = Classroom::factory()->create(['school_id' => $schoolB->id]);

        // Student 1 in School A
        $studentA1 = Student::create([
            'school_id' => $schoolA->id,
            'classroom_id' => $classroomA->id,
            'first_name' => 'Ali',
            'last_name' => 'Tazi',
            'gender' => Gender::Male,
        ]);

        $this->assertEquals("REG-{$year}-0001", $studentA1->registration_number);

        // Student 2 in School A
        $studentA2 = Student::create([
            'school_id' => $schoolA->id,
            'classroom_id' => $classroomA->id,
            'first_name' => 'Fatima',
            'last_name' => 'Zahra',
            'gender' => Gender::Female,
        ]);

        $this->assertEquals("REG-{$year}-0002", $studentA2->registration_number);

        // Student 1 in School B should restart at 0001
        $studentB1 = Student::create([
            'school_id' => $schoolB->id,
            'classroom_id' => $classroomB->id,
            'first_name' => 'Mehdi',
            'last_name' => 'Benali',
            'gender' => Gender::Male,
        ]);

        $this->assertEquals("REG-{$year}-0001", $studentB1->registration_number);

        // Preserves custom registration number if provided
        $studentA3 = Student::create([
            'school_id' => $schoolA->id,
            'classroom_id' => $classroomA->id,
            'first_name' => 'Custom',
            'last_name' => 'Reg',
            'gender' => Gender::Male,
            'registration_number' => 'CUSTOM-12345',
        ]);

        $this->assertEquals('CUSTOM-12345', $studentA3->registration_number);
    }

    public function test_ai_student_parser_service_parses_gemini_api_response(): void
    {
        config(['services.gemini.api_key' => 'fake-test-key']);

        $mockGeminiPayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    [
                                        'first_name' => 'Amine',
                                        'last_name' => 'Daoudi',
                                        'massar_code' => 'M134567890',
                                        'gender' => 'ذكر',
                                        'date_of_birth' => '2016-05-14',
                                        'guardian_name' => 'Rachid Daoudi',
                                        'guardian_phone' => '+212611223344',
                                    ],
                                    [
                                        'first_name' => 'Nour',
                                        'last_name' => 'Hakimi',
                                        'massar_code' => null,
                                        'gender' => 'Fille',
                                        'date_of_birth' => '2017-02-20',
                                        'guardian_name' => null,
                                        'guardian_phone' => null,
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($mockGeminiPayload, 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_students_').'.csv';
        file_put_contents($tempFile, 'Dummy content for AI parsing');

        $service = app(AiStudentParserService::class);
        $parsed = $service->parseFile($tempFile, 'text/csv');

        $this->assertCount(2, $parsed);

        $this->assertEquals('Amine', $parsed[0]['first_name']);
        $this->assertEquals('Daoudi', $parsed[0]['last_name']);
        $this->assertEquals('M134567890', $parsed[0]['massar_code']);
        $this->assertEquals('male', $parsed[0]['gender']);
        $this->assertEquals('2016-05-14', $parsed[0]['date_of_birth']);
        $this->assertEquals('+212611223344', $parsed[0]['guardian_phone']);

        $this->assertEquals('Nour', $parsed[1]['first_name']);
        $this->assertEquals('female', $parsed[1]['gender']);
        $this->assertNull($parsed[1]['guardian_name']);

        @unlink($tempFile);
    }

    public function test_ai_student_parser_service_fallback_csv(): void
    {
        config(['services.gemini.api_key' => null]);

        $csvContent = <<<'CSV'
first_name,last_name,massar_code,gender,date_of_birth
Younes,Alami,G123456789,male,2015-08-10
Kenza,Berrada,,female,2016-01-25
CSV;

        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv_').'.csv';
        file_put_contents($tempFile, $csvContent);

        $service = app(AiStudentParserService::class);
        $parsed = $service->parseFile($tempFile, 'text/csv');

        $this->assertCount(2, $parsed);
        $this->assertEquals('Younes', $parsed[0]['first_name']);
        $this->assertEquals('Alami', $parsed[0]['last_name']);
        $this->assertEquals('male', $parsed[0]['gender']);

        $this->assertEquals('Kenza', $parsed[1]['first_name']);
        $this->assertEquals('female', $parsed[1]['gender']);

        @unlink($tempFile);
    }

    public function test_import_students_associates_classroom_and_auto_generates_registration_numbers(): void
    {
        $school = School::factory()->create();
        $classroom = Classroom::factory()->create(['school_id' => $school->id]);

        $studentsData = [
            [
                'first_name' => 'Sami',
                'last_name' => 'Fassi',
                'massar_code' => 'M998877665',
                'gender' => 'male',
                'date_of_birth' => '2016-03-12',
                'guardian_name' => 'Ahmed Fassi',
                'guardian_phone' => '+212699887766',
            ],
            [
                'first_name' => 'Lina',
                'last_name' => 'Mansouri',
                'massar_code' => null,
                'gender' => 'female',
                'date_of_birth' => null,
                'guardian_name' => null,
                'guardian_phone' => null,
            ],
        ];

        $service = app(AiStudentParserService::class);
        $importedCount = $service->importStudents($studentsData, $school->id, $classroom->id);

        $this->assertEquals(2, $importedCount);

        $sami = Student::where('school_id', $school->id)->where('first_name', 'Sami')->firstOrFail();
        $this->assertEquals($classroom->id, $sami->classroom_id);
        $this->assertEquals('REG-'.date('Y').'-0001', $sami->registration_number);
        $this->assertNotNull($sami->guardian_id);
        $this->assertEquals('+212699887766', $sami->guardian->phone);

        $lina = Student::where('school_id', $school->id)->where('first_name', 'Lina')->firstOrFail();
        $this->assertEquals($classroom->id, $lina->classroom_id);
        $this->assertEquals('REG-'.date('Y').'-0002', $lina->registration_number);
        $this->assertNull($lina->guardian_id);
        $this->assertNull($lina->date_of_birth);
    }

    public function test_student_list_page_renders_with_ai_import_action(): void
    {
        $school = School::factory()->create(['slug' => 'ai-school']);
        $user = User::factory()->create(['school_id' => $school->id]);

        $response = $this->actingAs($user)->get("/admin/{$school->slug}/students");
        $response->assertSuccessful();
        $response->assertSee('Import with AI');
    }
}
