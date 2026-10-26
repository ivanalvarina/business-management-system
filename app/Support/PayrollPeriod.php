<?php

namespace App\Support;

use App\Models\Payroll;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PayrollPeriod
{
    /**
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, pay_date: CarbonImmutable}
     */
    public static function forCutoff(string $payrollMonth, string $payrollGroup, string $cutoffType): array
    {
        if (! in_array($payrollGroup, Payroll::creatableGroups(), true)) {
            throw new InvalidArgumentException('Payroll group must be regular or contractual.');
        }

        if (! in_array($cutoffType, Payroll::cutoffTypes(), true)) {
            throw new InvalidArgumentException('Cutoff type must be first_half or second_half.');
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $payrollMonth);

        if ($month === null || $month->format('Y-m') !== $payrollMonth) {
            throw new InvalidArgumentException('Payroll month must use YYYY-MM format.');
        }

        $isFirstHalf = $cutoffType === Payroll::CUTOFF_FIRST_HALF;
        $periodStart = $isFirstHalf ? $month->startOfMonth() : $month->day(16);
        $periodEnd = $isFirstHalf ? $month->day(15) : $month->endOfMonth();

        $scheduledPayDate = match ([$payrollGroup, $cutoffType]) {
            [Payroll::GROUP_REGULAR, Payroll::CUTOFF_FIRST_HALF] => $month->day(25),
            [Payroll::GROUP_REGULAR, Payroll::CUTOFF_SECOND_HALF] => $month->addMonthNoOverflow()->day(10),
            [Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_FIRST_HALF] => $month->day(15),
            [Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_SECOND_HALF] => $month->endOfMonth(),
            default => throw new InvalidArgumentException('Unsupported payroll schedule.'),
        };

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pay_date' => self::previousWeekday($scheduledPayDate),
        ];
    }

    public static function weekdays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $count = 0;

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }

    private static function previousWeekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->subDay();
        }

        return $date;
    }
}
