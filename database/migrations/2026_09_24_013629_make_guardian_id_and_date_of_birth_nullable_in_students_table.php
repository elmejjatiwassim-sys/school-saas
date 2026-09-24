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
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('guardian_id')->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->dropUnique('students_registration_number_unique');
            $table->unique(['school_id', 'registration_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'registration_number']);
            $table->unique('registration_number');
            $table->foreignId('guardian_id')->nullable(false)->change();
            $table->date('date_of_birth')->nullable(false)->change();
        });
    }
};
