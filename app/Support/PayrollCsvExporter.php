<?php

namespace App\Support;

use App\Models\Payroll;
use App\Models\PayrollItem;
use RuntimeException;

class PayrollCsvExporter
{
    private const HEADERS = [
        'Employee No.',
        'Employee Name',
        'Department',
        'Position',
        'Worked Days',
        'Basic Pay',
        'Regular OT Hours',
        'Regular OT Pay',
        '130% OT Hours',
        '130% OT Pay',
        '200% OT Hours',
        '200% OT Pay',
        'Late Hours',
        'Late Deduction',
        'Absent Days',
        'Absent Deduction',
        'Salary Adjustment',
        'Gross Pay',
        'SSS',
        'PhilHealth',
        'Pag-IBIG',
        'Withholding Tax',
        'Other Deduction',
        'Allowance',
        'Total Deductions',
        'Net Pay',
    ];

    /** @param resource $stream */
    public function write($stream, Payroll $payroll): void
    {
        $payroll->loadMissing('company');

        if (fwrite($stream, "\xEF\xBB\xBF") === false) {
            throw new RuntimeException('Unable to write the payroll CSV byte-order mark.');
        }

        $this->writeRow($stream, ['Company', $this->safeText($payroll->company->company_name)]);
        $this->writeRow($stream, ['Cutoff', $payroll->period_start->format('Y-m-d').' to '.$payroll->period_end->format('Y-m-d')]);
        $this->writeRow($stream, ['Pay date', $payroll->pay_date->format('Y-m-d')]);
        $this->writeRow($stream, ['Status', ucfirst($payroll->status)]);
        $this->writeRow($stream, []);
        $this->writeRow($stream, self::HEADERS);

        $totals = array_fill(0, count(self::HEADERS), 0.0);

        $payroll->items()->cursor()->each(function (PayrollItem $item) use ($stream, &$totals): void {
            $row = $this->itemRow($item);

            for ($index = 4; $index < count($row); $index++) {
                $totals[$index] += (float) $row[$index];
            }

            $this->writeRow($stream, $row);
        });

        $totalRow = array_fill(0, count(self::HEADERS), '');
        $totalRow[1] = 'TOTAL';

        for ($index = 4; $index < count($totalRow); $index++) {
            $totalRow[$index] = $this->number($totals[$index]);
        }

        $this->writeRow($stream, $totalRow);
    }

    /** @return array<int, string> */
    private function itemRow(PayrollItem $item): array
    {
        return [
            $this->safeText($item->employee_number),
            $this->safeText($item->employee_name),
            $this->safeText((string) $item->department),
            $this->safeText($item->job_title),
            $this->number($item->days_worked),
            $this->number($item->period_basic_pay),
            $this->number($item->regular_ot_hours),
            $this->number($item->regular_ot_pay),
            $this->number($item->special_holiday_hours),
            $this->number($item->special_holiday_pay),
            $this->number($item->regular_holiday_hours),
            $this->number($item->regular_holiday_pay),
            $this->number($item->late_hours),
            $this->number($item->late_deduction),
            $this->number($item->absent_days),
            $this->number($item->absence_deduction),
            $this->number($item->salary_adjustment),
            $this->number($item->gross_pay),
            $this->number($item->sss_contribution),
            $this->number($item->philhealth_contribution),
            $this->number($item->pagibig_contribution),
            $this->number($item->withholding_tax),
            $this->number($item->other_deduction),
            $this->number($item->allowance),
            $this->number($item->total_deductions),
            $this->number($item->net_pay),
        ];
    }

    /**
     * @param  resource  $stream
     * @param  array<int, string>  $values
     */
    private function writeRow($stream, array $values): void
    {
        if (fputcsv($stream, $values, ',', '"', '') === false) {
            throw new RuntimeException('Unable to write a payroll CSV row.');
        }
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function number(string|float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
