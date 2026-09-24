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
        // 1. In students table: add monthly_tuition_fee
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'monthly_tuition_fee')) {
                $table->decimal('monthly_tuition_fee', 10, 2)->default(0.00)->after('opening_balance');
            }
        });

        // 2. In schools table: add stamp_signature_path
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'stamp_signature_path')) {
                $table->string('stamp_signature_path')->nullable()->after('favicon_path');
            }
        });

        // 3. In payments table: add cheque fields and update 'check' to 'cheque'
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'cheque_number')) {
                $table->string('cheque_number')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('payments', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('cheque_number');
            }
        });

        try {
            DB::table('payments')->where('payment_method', 'check')->update(['payment_method' => 'cheque']);
        } catch (Throwable $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'monthly_tuition_fee')) {
                $table->dropColumn('monthly_tuition_fee');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'stamp_signature_path')) {
                $table->dropColumn('stamp_signature_path');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('payments', 'cheque_number')) {
                $table->dropColumn('cheque_number');
            }
        });
    }
};
