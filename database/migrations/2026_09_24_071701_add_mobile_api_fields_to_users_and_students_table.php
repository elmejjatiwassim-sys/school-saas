<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->index()->after('email');
            }
            if (! Schema::hasColumn('users', 'guardian_id')) {
                $table->foreignId('guardian_id')->nullable()->after('student_id')->constrained('guardians')->nullOnDelete();
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'photo')) {
                $table->string('photo')->nullable()->after('massar_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'photo')) {
                $table->dropColumn('photo');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'guardian_id')) {
                $table->dropForeign(['guardian_id']);
                $table->dropColumn('guardian_id');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
