<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payroll_items')
            ->whereIn('payroll_id', DB::table('payrolls')->select('id')->where('status', 'draft'))
            ->update(['days_worked' => DB::raw('payroll_days_basis')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing attendance values cannot be reconstructed safely.
    }
};
