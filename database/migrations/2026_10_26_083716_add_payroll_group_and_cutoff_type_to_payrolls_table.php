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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('payroll_group', 20)->default('regular')->after('company_id');
            $table->string('cutoff_type', 20)->default('first_half')->after('payroll_group');
        });

        DB::table('payrolls')->orderBy('id')->each(function (object $payroll): void {
            $employmentTypes = DB::table('payroll_items')
                ->where('payroll_id', $payroll->id)
                ->distinct()
                ->pluck('employment_type');
            $hasContractualEmployees = $employmentTypes->contains('contractual');
            $hasRegularEmployees = $employmentTypes->contains(fn (mixed $type): bool => $type !== 'contractual');

            $payrollGroup = match (true) {
                $hasContractualEmployees && $hasRegularEmployees => 'mixed',
                $hasContractualEmployees => 'contractual',
                default => 'regular',
            };

            DB::table('payrolls')->where('id', $payroll->id)->update([
                'payroll_group' => $payrollGroup,
                'cutoff_type' => substr((string) $payroll->period_start, 8, 2) === '16'
                    ? 'second_half'
                    : 'first_half',
            ]);
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'period_start', 'period_end']);
            $table->unique(['company_id', 'payroll_group', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'payroll_group', 'period_start', 'period_end']);
            $table->dropColumn(['payroll_group', 'cutoff_type']);
            $table->unique(['company_id', 'period_start', 'period_end']);
        });
    }
};
