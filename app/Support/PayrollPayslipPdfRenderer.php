<?php

namespace App\Support;

use App\Models\Payroll;
use App\Models\PayrollItem;

class PayrollPayslipPdfRenderer
{
    public function render(PayrollItem $item): string
    {
        return $this->renderItems(
            [$item],
            $item->employee_number.'-'.$item->payroll->pay_date->format('Y-m-d').'.pdf',
        );
    }

    public function renderPayroll(Payroll $payroll): string
    {
        $payroll->loadMissing(['company', 'items']);
        $payroll->items->each(fn (PayrollItem $item) => $item->setRelation('payroll', $payroll));

        return $this->renderItems(
            $payroll->items,
            'payroll-'.$payroll->period_start->format('Y-m-d').'-'.$payroll->period_end->format('Y-m-d').'.pdf',
        );
    }

    /** @param iterable<PayrollItem> $items */
    private function renderItems(iterable $items, string $filename): string
    {
        $pdf = new PdfCanvas('P', 'mm', 'A4');
        $pdf->SetMargins(15, 12, 15);
        $pdf->SetAutoPageBreak(true, 12);
        $itemCount = 0;

        foreach ($items as $item) {
            $itemCount++;
            $item->loadMissing('payroll.company');
            $payroll = $item->payroll;
            $company = $payroll->company;
            $scheduledWeekdays = PayrollPeriod::weekdays($payroll->period_start->toImmutable(), $payroll->period_end->toImmutable());
            $pdf->AddPage();

            $pdf->SetFont('helvetica', 'B', 15);
            $pdf->Cell(0, 7, $this->text($company->company_name), 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 5, $this->text((string) $company->address), 0, 1, 'C');
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'PAYSLIP', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 9);
            $this->pair($pdf, 'Employee', $item->employee_name, 'Employee no.', $item->employee_number);
            $this->pair($pdf, 'Position', $item->job_title, 'Department', $item->department ?: '-');
            $this->pair($pdf, 'Cutoff', $payroll->period_start->format('M d').' - '.$payroll->period_end->format('M d, Y'), 'Pay date', $payroll->pay_date->format('M d, Y'));
            $this->pair($pdf, 'Bank', $item->bank_name ?: '-', 'Account', $item->bank_account_last_four ? '****'.$item->bank_account_last_four : '-');
            $pdf->Ln(5);

            $pdf->SetFillColor(240, 242, 245);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(90, 7, 'ATTENDANCE', 1, 0, 'L', true);
            $pdf->Cell(90, 7, 'RATES', 1, 1, 'L', true);
            $pdf->SetFont('helvetica', '', 8);
            $this->pair($pdf, 'Days worked', $this->number($item->days_worked), 'Payroll basis', $this->number($item->payroll_days_basis).' days', true);
            $this->pair($pdf, 'Absent days', $this->number($item->absent_days), 'Scheduled weekdays', (string) $scheduledWeekdays, true);
            $this->pair($pdf, 'Late hours', $this->number($item->late_hours), 'Daily / hourly rate', $this->money($item->daily_rate).' / '.$this->money($item->hourly_rate), true);
            $pdf->Ln(5);

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(90, 7, 'EARNINGS', 1, 0, 'L', true);
            $pdf->Cell(90, 7, 'DEDUCTIONS', 1, 1, 'L', true);
            $pdf->SetFont('helvetica', '', 8);
            $earnings = [
                ['Basic pay', $item->period_basic_pay],
                ['Regular OT ('.$this->number($item->regular_ot_hours).' hrs)', $item->regular_ot_pay],
                ['130% OT ('.$this->number($item->special_holiday_hours).' hrs)', $item->special_holiday_pay],
                ['200% OT ('.$this->number($item->regular_holiday_hours).' hrs)', $item->regular_holiday_pay],
                ['Salary adjustment', $item->salary_adjustment],
                ['Allowance', $item->allowance],
            ];
            $deductions = [
                ['Absences', $item->absence_deduction],
                ['Lates', $item->late_deduction],
                ['SSS', $item->sss_contribution],
                ['PhilHealth', $item->philhealth_contribution],
                ['Pag-IBIG', $item->pagibig_contribution],
                ['Withholding tax', $item->withholding_tax],
                ['Other deduction', $item->other_deduction],
            ];

            for ($index = 0; $index < max(count($earnings), count($deductions)); $index++) {
                $earning = $earnings[$index] ?? ['', null];
                $deduction = $deductions[$index] ?? ['', null];
                $this->moneyRow($pdf, $earning[0], $earning[1], $deduction[0], $deduction[1]);
            }

            $pdf->SetFont('helvetica', 'B', 9);
            $this->moneyRow($pdf, 'GROSS PAY', $item->gross_pay, 'TOTAL DEDUCTIONS', $item->total_deductions, true);
            $pdf->Ln(6);
            $pdf->SetFillColor(226, 244, 234);
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Cell(125, 11, 'NET PAY', 1, 0, 'R', true);
            $pdf->Cell(55, 11, $this->money($item->net_pay), 1, 1, 'R', true);
            $pdf->Ln(8);
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->Cell(0, 5, 'Computer-generated payslip. No signature is required.', 0, 1, 'C');

        }

        if ($itemCount === 0) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'NO PAYSLIPS AVAILABLE', 0, 1, 'C');
        }

        return $pdf->Output($filename, 'S');
    }

    private function pair(PdfCanvas $pdf, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue, bool $border = false): void
    {
        $lineBorder = $border ? 1 : 0;
        $pdf->Cell(28, 6, $this->text($leftLabel), $lineBorder);
        $pdf->Cell(62, 6, $this->text($leftValue), $lineBorder);
        $pdf->Cell(28, 6, $this->text($rightLabel), $lineBorder);
        $pdf->Cell(62, 6, $this->text($rightValue), $lineBorder, 1);
    }

    private function moneyRow(PdfCanvas $pdf, string $leftLabel, string|float|int|null $leftValue, string $rightLabel, string|float|int|null $rightValue, bool $fill = false): void
    {
        $pdf->Cell(57, 6, $this->text($leftLabel), 1, 0, 'L', $fill);
        $pdf->Cell(33, 6, $leftValue === null ? '' : $this->money($leftValue), 1, 0, 'R', $fill);
        $pdf->Cell(57, 6, $this->text($rightLabel), 1, 0, 'L', $fill);
        $pdf->Cell(33, 6, $rightValue === null ? '' : $this->money($rightValue), 1, 1, 'R', $fill);
    }

    private function money(string|float|int $value): string
    {
        return 'PHP '.number_format((float) $value, 2);
    }

    private function number(string|float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function text(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return $converted === false ? $value : $converted;
    }
}
