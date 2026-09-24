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
            $table->foreignId('school_id')->nullable()->change();
            $table->boolean('is_super_admin')->default(false)->index();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('city')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('city');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->decimal('price_per_student', 8, 2)->default(1.50)->after('favicon_path');
            $table->decimal('monthly_minimum_charge', 8, 2)->default(300.00)->after('price_per_student');
            $table->string('subscription_status')->default('active')->after('monthly_minimum_charge');
            $table->string('stripe_customer_id')->nullable()->after('subscription_status');
            $table->string('stripe_payment_method_id')->nullable()->after('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'city',
                'logo_path',
                'favicon_path',
                'price_per_student',
                'monthly_minimum_charge',
                'subscription_status',
                'stripe_customer_id',
                'stripe_payment_method_id',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
            $table->foreignId('school_id')->nullable(false)->change();
        });
    }
};
