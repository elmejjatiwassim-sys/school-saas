<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('payment_token')->nullable()->unique()->after('status');
        });

        // Backfill payment_token for any existing invoices
        $invoices = DB::table('invoices')->whereNull('payment_token')->get();
        foreach ($invoices as $inv) {
            DB::table('invoices')->where('id', $inv->id)->update([
                'payment_token' => (string) Str::uuid(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_token');
        });
    }
};
