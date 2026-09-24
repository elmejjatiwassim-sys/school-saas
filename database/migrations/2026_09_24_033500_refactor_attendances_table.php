<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendance_records') && ! Schema::hasTable('attendances')) {
            Schema::rename('attendance_records', 'attendances');
        } elseif (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->date('date');
                $table->string('session')->nullable();
                $table->string('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendance_records_school_id_student_id_date_session_unique');
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_school_id_student_id_date_session_unique');
        } else {
            try {
                DB::statement('DROP INDEX IF EXISTS "attendance_records_school_id_student_id_date_session_unique"');
                DB::statement('DROP INDEX IF EXISTS "attendances_school_id_student_id_date_session_unique"');
            } catch (Throwable $e) {
            }
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'session')) {
                $table->string('session')->nullable()->change();
            } else {
                $table->string('session')->nullable();
            }

            if (! Schema::hasColumn('attendances', 'timetable_id')) {
                $table->foreignId('timetable_id')->nullable()->constrained('timetables')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('attendances', 'recorded_by_id')) {
                $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (Schema::hasColumn('attendances', 'status')) {
                $table->string('status')->default('absent')->change();
            } else {
                $table->string('status')->default('absent');
            }

            if (! Schema::hasColumn('attendances', 'late_arrival_time')) {
                $table->time('late_arrival_time')->nullable();
            }

            if (! Schema::hasColumn('attendances', 'guardian_justification_note')) {
                $table->text('guardian_justification_note')->nullable();
            }

            if (! Schema::hasColumn('attendances', 'guardian_justification_attachment')) {
                $table->string('guardian_justification_attachment')->nullable();
            }

            if (! Schema::hasColumn('attendances', 'justification_status')) {
                $table->string('justification_status')->default('pending');
            }

            if (! Schema::hasColumn('attendances', 'reviewed_by_id')) {
                $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            }

            try {
                $table->unique(['student_id', 'timetable_id', 'date']);
            } catch (Throwable $e) {
            }
        });

        if (! Schema::hasTable('attendance_records')) {
            try {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('CREATE OR REPLACE VIEW attendance_records AS SELECT * FROM attendances');
                } else {
                    DB::statement('CREATE VIEW IF NOT EXISTS attendance_records AS SELECT * FROM attendances');
                }
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS attendance_records');
        } catch (Throwable $e) {
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                try {
                    $table->dropUnique(['student_id', 'timetable_id', 'date']);
                } catch (Throwable $e) {
                }
                $table->dropConstrainedForeignId('timetable_id');
                $table->dropConstrainedForeignId('recorded_by_id');
                $table->dropConstrainedForeignId('reviewed_by_id');
                $table->dropColumn([
                    'late_arrival_time',
                    'guardian_justification_note',
                    'guardian_justification_attachment',
                    'justification_status',
                ]);
            });

            Schema::rename('attendances', 'attendance_records');
        }
    }
};
