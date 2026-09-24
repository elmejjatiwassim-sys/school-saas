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
        Schema::create('platform_subscriptions_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('billing_month', 7);
            $table->integer('active_students_count')->default(0);
            $table->decimal('rate_applied', 8, 2)->default(1.50);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->string('stripe_invoice_id')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_subscriptions_invoices');
    }
};
