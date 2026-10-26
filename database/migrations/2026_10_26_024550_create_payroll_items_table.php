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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 50);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('job_title');
            $table->string('pay_frequency');
            $table->decimal('monthly_basic_salary', 15, 2);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_last_four', 4)->nullable();
            $table->decimal('hours_per_day', 5, 2);
            $table->decimal('annual_workdays_divisor', 6, 2);
            $table->decimal('regular_ot_multiplier', 5, 2);
            $table->decimal('special_holiday_multiplier', 5, 2);
            $table->decimal('special_holiday_ot_multiplier', 5, 2);
            $table->decimal('regular_holiday_multiplier', 5, 2);
            $table->decimal('regular_holiday_ot_multiplier', 5, 2);
            $table->decimal('days_worked', 6, 2)->default(0);
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('late_hours', 8, 2)->default(0);
            $table->decimal('regular_ot_hours', 8, 2)->default(0);
            $table->decimal('special_holiday_hours', 8, 2)->default(0);
            $table->decimal('special_holiday_ot_hours', 8, 2)->default(0);
            $table->decimal('regular_holiday_hours', 8, 2)->default(0);
            $table->decimal('regular_holiday_ot_hours', 8, 2)->default(0);
            $table->decimal('period_basic_pay', 15, 2)->default(0);
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
            $table->decimal('absence_deduction', 15, 2)->default(0);
            $table->decimal('late_deduction', 15, 2)->default(0);
            $table->decimal('regular_ot_pay', 15, 2)->default(0);
            $table->decimal('special_holiday_pay', 15, 2)->default(0);
            $table->decimal('special_holiday_ot_pay', 15, 2)->default(0);
            $table->decimal('regular_holiday_pay', 15, 2)->default(0);
            $table->decimal('regular_holiday_ot_pay', 15, 2)->default(0);
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
            $table->index('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
