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
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->decimal('payroll_days_basis', 5, 2)->default(13)->after('hours_per_day');
            $table->decimal('monthly_workdays_divisor', 5, 2)->default(26)->after('payroll_days_basis');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('payroll_days_basis', 5, 2)->default(13)->after('hours_per_day');
            $table->decimal('monthly_workdays_divisor', 5, 2)->default(26)->after('payroll_days_basis');
            $table->decimal('salary_adjustment', 15, 2)->default(0)->after('regular_holiday_ot_hours');
            $table->decimal('sss_contribution', 15, 2)->default(0)->after('salary_adjustment');
            $table->decimal('philhealth_contribution', 15, 2)->default(0)->after('sss_contribution');
            $table->decimal('pagibig_contribution', 15, 2)->default(0)->after('philhealth_contribution');
            $table->decimal('withholding_tax', 15, 2)->default(0)->after('pagibig_contribution');
            $table->decimal('other_deduction', 15, 2)->default(0)->after('withholding_tax');
            $table->decimal('allowance', 15, 2)->default(0)->after('other_deduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_days_basis',
                'monthly_workdays_divisor',
                'salary_adjustment',
                'sss_contribution',
                'philhealth_contribution',
                'pagibig_contribution',
                'withholding_tax',
                'other_deduction',
                'allowance',
            ]);
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['payroll_days_basis', 'monthly_workdays_divisor']);
        });
    }
};
