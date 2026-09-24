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
        Schema::table('schools', function (Blueprint $table) {
            $table->string('code', 4)->nullable()->unique()->index()->after('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('username')->nullable()->unique()->index()->after('name');
            $table->string('role')->default('admin')->after('email');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Backfill schools with unique 3-4 letter uppercase code
        $schools = DB::table('schools')->get();
        foreach ($schools as $school) {
            $code = $this->generateSchoolCode($school->name);
            DB::table('schools')->where('id', $school->id)->update(['code' => $code]);
        }

        // Backfill existing users with username
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $school = $user->school_id ? DB::table('schools')->where('id', $user->school_id)->first() : null;
            $schoolCode = $school?->code ?? 'SCH';
            $username = $schoolCode.'-'.rand(1000, 9999);
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $schoolCode.'-'.rand(1000, 9999);
            }
            DB::table('users')->where('id', $user->id)->update([
                'username' => $username,
                'role' => 'admin',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn(['username', 'role', 'is_active']);
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    protected function generateSchoolCode(string $name): string
    {
        // Extract alpha characters
        $clean = preg_replace('/[^A-Za-z]/', '', $name);
        $candidate = strtoupper(substr($clean, 0, 4));

        if (strlen($candidate) < 3) {
            $candidate = str_pad($candidate, 3, 'X');
        }

        $code = $candidate;
        $counter = 1;
        while (DB::table('schools')->where('code', $code)->exists()) {
            $code = substr($candidate, 0, 2).$counter.rand(0, 9);
            $counter++;
        }

        return substr($code, 0, 4);
    }
};
