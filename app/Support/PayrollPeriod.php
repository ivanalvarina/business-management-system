<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PayrollPeriod
{
    /**
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, pay_date: CarbonImmutable}
     */
    public static function forPayday(string $payrollMonth, int $payday): array
    {
        if (! in_array($payday, [10, 25], true)) {
            throw new InvalidArgumentException('Payday must be 10 or 25.');
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $payrollMonth);

        if ($month === false || $month->format('Y-m') !== $payrollMonth) {
            throw new InvalidArgumentException('Payroll month must use YYYY-MM format.');
        }

        if ($payday === 25) {
            return [
                'period_start' => $month->startOfMonth(),
                'period_end' => $month->day(15),
                'pay_date' => $month->day(25),
            ];
        }

        return [
            'period_start' => $month->day(16),
            'period_end' => $month->endOfMonth(),
            'pay_date' => $month->addMonthNoOverflow()->day(10),
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
}
