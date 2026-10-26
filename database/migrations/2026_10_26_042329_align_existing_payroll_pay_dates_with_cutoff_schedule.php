<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payrolls')
            ->select(['id', 'period_start'])
            ->orderBy('id')
            ->chunkById(100, function ($payrolls): void {
                foreach ($payrolls as $payroll) {
                    DB::table('payrolls')
                        ->where('id', $payroll->id)
                        ->update(['pay_date' => $this->payDateForCutoff($payroll->period_start)->toDateString()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payrolls')
            ->select(['id', 'period_start'])
            ->orderBy('id')
            ->chunkById(100, function ($payrolls): void {
                foreach ($payrolls as $payroll) {
                    $payDate = $this->payDateForCutoff($payroll->period_start);

                    while ($payDate->isWeekend()) {
                        $payDate = $payDate->subDay();
                    }

                    DB::table('payrolls')
                        ->where('id', $payroll->id)
                        ->update(['pay_date' => $payDate->toDateString()]);
                }
            });
    }

    private function payDateForCutoff(string $periodStart): CarbonImmutable
    {
        $cutoffStart = CarbonImmutable::parse($periodStart);

        return $cutoffStart->day <= 15
            ? $cutoffStart->day(25)
            : $cutoffStart->addMonthNoOverflow()->day(10);
    }
};
