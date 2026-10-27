<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;

class PayrollPayslipPdfRenderer
{
    public function __construct(private readonly PayrollVoucherBreakdown $voucherBreakdown) {}

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
            $pdf->AddPage();

            if ($item->employment_type === Employee::EMPLOYMENT_CONTRACTUAL) {
                $this->renderContractualVoucher($pdf, $item);

                continue;
            }

            $this->renderPayslip($pdf, $item);
        }

        if ($itemCount === 0) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'NO PAYSLIPS AVAILABLE', 0, 1, 'C');
        }

        return $pdf->Output($filename, 'S');
    }

    private function renderContractualVoucher(PdfCanvas $pdf, PayrollItem $item): void
    {
        $this->renderContractualVoucherCopy($pdf, $item, 10, 'EMPLOYEE COPY');
        $this->cutLine($pdf, 148.5);
        $this->renderContractualVoucherCopy($pdf, $item, 155, 'COMPANY COPY');
    }

    private function renderPayslip(PdfCanvas $pdf, PayrollItem $item): void
    {
        $this->renderPayslipCopy($pdf, $item, 10, 'EMPLOYEE COPY');
        $this->cutLine($pdf, 148.5);
        $this->renderPayslipCopy($pdf, $item, 155, 'EMPLOYER COPY');
    }

    private function renderPayslipCopy(PdfCanvas $pdf, PayrollItem $item, float $top, string $copyLabel): void
    {
        $payroll = $item->payroll;
        $company = $payroll->company;
        $left = 15;
        $rightX = 115;
        $period = $payroll->period_start->format('F d').'-'.$payroll->period_end->format('d, Y');

        // Header
        $pdf->SetXY($left, $top);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(130, 6, $this->text(mb_strtoupper($company->company_name)));
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell(50, 6, $copyLabel, 0, 1, 'R');

        $pdf->SetX($left);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(130, 4, $this->text((string) $company->address));
        $pdf->Cell(50, 4, 'PAYSLIP', 0, 1, 'R');

        // Employee
        $pdf->SetXY($left, $top + 15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(130, 6, $this->text(mb_strtoupper($item->employee_name)));
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(50, 6, 'Emp. no.: '.$this->text($item->employee_number), 0, 1, 'R');

        $pdf->SetX($left);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(130, 4, $this->text($item->job_title.($item->department ? ' / '.$item->department : '')));
        $pdf->Cell(50, 4, 'Pay date: '.$payroll->pay_date->format('M d, Y'), 0, 1, 'R');

        // Rates
        $y = $top + 30;
        $pdf->SetFont('helvetica', '', 8);
        $this->line($pdf, $left, $y, 'Rate / Day', $this->amount($item->daily_rate));
        $this->line($pdf, $left, $y + 5, 'Rate / Hr', $this->amount($item->hourly_rate));

        // Earnings (left) — label : amount ( qty )
        $y = $top + 43;
        $earnings = [
            ['Basic Pay', $item->period_basic_pay, $item->days_worked],
            ['Late', $item->late_deduction, $item->late_hours],
            ['Absent', $item->absence_deduction, $item->absent_days],
            ['Regular O.T. Pay', $item->regular_ot_pay, $item->regular_ot_hours],
            ['130% O.T. Pay', $item->special_holiday_pay, $item->special_holiday_hours],
            ['200% O.T. Pay', $item->regular_holiday_pay, $item->regular_holiday_hours],
        ];

        foreach ($earnings as $i => [$label, $amount, $qty]) {
            $this->line($pdf, $left, $y + ($i * 5), $label, $this->amount($amount), '('.$this->number($qty).')');
        }
        $this->line($pdf, $left, $y + 33, 'Sal Adj (+/-)', $this->amount($item->salary_adjustment));

        // Deductions (right)
        $deductions = [
            ['S.S.S.', $item->sss_contribution],
            ['PhilHealth', $item->philhealth_contribution],
            ['Pag-ibig', $item->pagibig_contribution],
            ['W/Tax', $item->withholding_tax],
            ['C.A. / others', $item->other_deduction],
        ];

        foreach ($deductions as $i => [$label, $amount]) {
            $this->line($pdf, $rightX, $y + ($i * 5), $label, $this->amount($amount), null, 32, 36);
        }

        $pdf->Line($rightX + 35, $y + 25.5, $rightX + 80, $y + 25.5);
        $pdf->SetFont('helvetica', 'B', 8);
        $this->line($pdf, $rightX, $y + 28, 'Deductions', $this->amount($item->total_deductions), null, 32, 36);

        // Vertical divider sa pagitan ng earnings at deductions
        $dividerX = $left + 92;
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line($dividerX, $top + 30, $dividerX, $top + 83);
        $pdf->SetDrawColor(0, 0, 0);

        // Totals
        $y = $top + 87;
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line($left, $y - 2, $left + 180, $y - 2);
        $pdf->SetDrawColor(0, 0, 0);

        $pdf->SetXY($left, $y);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(22, 6, 'GROSS PAY');
        $pdf->Cell(4, 6, ':');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(28, 6, $this->amount($item->gross_pay), 0, 0, 'R');

        $pdf->SetX($left + 62);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(24, 6, 'ALLOWANCE :');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(24, 6, $this->amount($item->allowance), 0, 0, 'R');

        $pdf->SetX($left + 118);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(20, 6, 'NET PAY :');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(42, 6, $this->amount($item->net_pay), 'B', 1, 'R');

        // Acknowledgment
        $ackY = $top + 108;

        $pdf->SetXY($left, $ackY);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Write(4, 'I hereby acknowledge to receive the amount of ');
        $pdf->SetFont('helvetica', 'BU', 7);          // bold + underline
        $pdf->Write(4, 'Php '.$this->amount($item->net_pay));

        $pdf->SetXY($left, $ackY + 4);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Write(4, 'as net pay for period of ');
        $pdf->SetFont('helvetica', 'BI', 7);          // bold + italic
        $pdf->Write(4, $period);

        // Signature (walang pagbabago)
        $pdf->SetXY($left + 125, $ackY + 2);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->Cell(55, 4, $this->text(mb_strtoupper($item->employee_name)), 'B', 1, 'C');
        $pdf->SetX($left + 125);
        $pdf->SetFont('helvetica', '', 5);
        $pdf->Cell(55, 4, 'Signature & Date', 0, 1, 'C');
    }

    private function line(PdfCanvas $pdf, float $x, float $y, string $label, string $amount, ?string $qty = null, float $labelW = 32, float $amountW = 28): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($labelW, 5, $this->text($label));
        $pdf->Cell(4, 5, ':');
        $pdf->Cell($amountW, 5, $amount, 0, 0, 'R');

        if ($qty !== null) {
            $pdf->Cell(20, 5, $qty, 0, 0, 'R');
        }
    }

    private function amount(string|float|int|null $value): string
    {
        return number_format((float) $value, 2);
    }

    private function renderContractualVoucherCopy(PdfCanvas $pdf, PayrollItem $item, float $top, string $copyLabel): void
    {
        $payroll = $item->payroll;
        $company = $payroll->company;
        $left = 15;
        $width = 180;

        $pdf->SetXY($left, $top);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(105, 6, $this->text($company->company_name));
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(30, 6, 'Voucher No.:', 0, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(45, 6, $this->text($item->voucher_no ?: 'DRAFT'), 'B', 1, 'C');

        $pdf->SetXY($left, $top + 6);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->Cell($width, 4, $copyLabel, 0, 1, 'R');

        $pdf->SetXY($left, $top + 13);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(35, 7, 'Transaction Type:', 1, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(70, 7, 'Professional Fee', 1);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(25, 7, 'Pay Date:', 1, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(50, 7, $payroll->pay_date->format('M d, Y'), 1, 1);

        $pdf->SetX($left);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(35, 7, 'Payee:', 1, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(145, 7, $this->text(mb_strtoupper($item->employee_name)), 1, 1);

        $pdf->SetX($left);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(35, 7, 'Payroll Cutoff:', 1, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(145, 7, $payroll->period_start->format('M d').' - '.$payroll->period_end->format('M d, Y'), 1, 1);

        $pdf->SetX($left);
        $pdf->SetFillColor(240, 242, 245);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(125, 6, 'Gross Pay', 1, 0, 'L', true);
        $pdf->Cell(55, 6, $this->money($item->gross_pay), 1, 1, 'R', true);

        $deductions = $this->voucherBreakdown->deductions($item);

        foreach ($deductions as $index => $deduction) {
            $pdf->SetX($left);
            $pdf->SetFont('helvetica', '', 7);
            $label = $index === 0 ? 'Less: '.$deduction['label'] : $deduction['label'];
            $pdf->Cell(125, 5, $this->text($label), 1);
            $pdf->Cell(55, 5, $this->money($deduction['amount']), 1, 1, 'R');
        }

        if ($deductions !== []) {
            $pdf->SetX($left);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(125, 5, 'Total Deductions', 1);
            $pdf->Cell(55, 5, $this->money($item->total_deductions), 1, 1, 'R');
        }

        $pdf->SetX($left);
        $pdf->SetFillColor(226, 244, 234);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(125, 7, 'NET PAY', 1, 0, 'R', true);
        $pdf->Cell(55, 7, $this->money($item->net_pay), 1, 1, 'R', true);

        $acknowledgmentY = $top + 108;
        $pdf->SetXY($left + 5, $acknowledgmentY);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(115, 5, 'I acknowledge receipt of the net amount of '.$this->money($item->net_pay).'.');

        $pdf->SetXY($left + 125, $acknowledgmentY);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->Cell(50, 5, $this->text(mb_strtoupper($item->employee_name)), 'B', 1, 'C');
        $pdf->SetX($left + 125);
        $pdf->SetFont('helvetica', '', 5);
        $pdf->Cell(50, 4, 'Signature and Date', 0, 1, 'C');
    }

    private function cutLine(PdfCanvas $pdf, float $y): void
    {
        for ($x = 10; $x < 200; $x += 5) {
            $pdf->Line($x, $y, min($x + 3, 200), $y);
        }
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
