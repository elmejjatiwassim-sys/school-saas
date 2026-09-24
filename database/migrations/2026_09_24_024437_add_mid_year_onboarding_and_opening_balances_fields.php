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
        Schema::table('schools', function (Blueprint $table) {
            $table->date('billing_start_date')->default(now()->toDateString())->after('is_active');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->decimal('opening_balance', 8, 2)->default(0.00)->after('status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type')->default('monthly_fee')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('billing_start_date');
        });
    }
};
