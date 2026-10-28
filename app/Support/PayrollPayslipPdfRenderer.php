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
            ...$this->payslipOtherDeductions($item),
        ];

        foreach ($deductions as $i => [$label, $amount]) {
            $this->line($pdf, $rightX, $y + ($i * 4), $label, $this->amount($amount), null, 32, 36);
        }

        $deductionTotalY = $y + (count($deductions) * 4) + 1;
        $pdf->Line($rightX + 35, $deductionTotalY, $rightX + 80, $deductionTotalY);
        $pdf->SetFont('helvetica', 'B', 8);
        $this->line($pdf, $rightX, $deductionTotalY + 2.5, 'Deductions', $this->amount($item->total_deductions), null, 32, 36);

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

    /** @return array<int, array{0: string, 1: float}> */
    private function payslipOtherDeductions(PayrollItem $item): array
    {
        $labels = [
            'cash_advance' => 'Cash advance',
            'company_loan' => 'Company loan',
            'equipment_accountability' => 'Equipment / acct.',
            'other' => 'Other',
        ];
        $deductions = collect($item->itemizedOtherDeductions())
            ->groupBy('type')
            ->map(fn ($rows, string $type): array => [
                $labels[$type] ?? 'Other',
                (float) collect($rows)->sum(fn (array $deduction): float => $deduction['amount']),
            ])
            ->filter(fn (array $deduction): bool => $deduction[1] > 0)
            ->values();

        if ($deductions->isEmpty() && (float) $item->other_deduction > 0) {
            $deductions->push(['C.A. / others', (float) $item->other_deduction]);
        }

        return $deductions->all();
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

    /**
     * One A4 page: Employee Copy (top) + Employer Copy (bottom).
     * Call this once per PayrollItem.
     */
    private function renderContractualVoucherPage(PdfCanvas $pdf, PayrollItem $item): void
    {
        $pdf->SetAutoPageBreak(false, 0);   // iwas page break sa gitna ng voucher
        $pdf->AddPage();

        $this->renderContractualVoucherCopy($pdf, $item, 8, 'EMPLOYEE COPY');
        $this->drawCutLine($pdf, 148.5);
        $this->renderContractualVoucherCopy($pdf, $item, 153, 'EMPLOYER COPY');
    }

    private function drawCutLine(PdfCanvas $pdf, float $y): void
    {
        $pdf->SetDrawColor(80, 80, 80);
        $pdf->SetLineWidth(0.2);

        for ($x = 10; $x < 200; $x += 4) {
            $pdf->Line($x, $y, $x + 2, $y);
        }
    }

    private function renderContractualVoucherCopy(PdfCanvas $pdf, PayrollItem $item, float $top, string $copyLabel): void
    {
        $payroll = $item->payroll;
        $company = $payroll->company;
        $left = 15;
        $width = 180;
        $rowH = 10;   // main rows
        $netH = 8;    // net pay row
        $amount = 'Php '.number_format((float) $item->net_pay, 2);

        $deductions = $this->voucherBreakdown->deductions($item);

        // Payroll Cutoff + Gross Pay + deductions + Total Deductions (kung meron)
        $breakRows = 2 + count($deductions) + ($deductions !== [] ? 1 : 0);

        // ---------- Auto-fit: kasya sa kalahating A4 ----------
        $maxCopyH = 132;                        // max height ng isang copy (mm)
        $fixedH = 14                            // header
            + ($rowH * 3)                       // 3 main rows
            + $netH + 4                         // net pay + padding
            + 6 + 12;                           // gap + acknowledgment/signature
        $lineH = max(3.4, min(6, ($maxCopyH - $fixedH) / $breakRows));
        $fontSize = $lineH >= 4.5 ? 7 : 6;

        $boxH = ($rowH * 3) + ($lineH * $breakRows) + $netH + 4;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        // ---------- Header ----------
        $pdf->SetXY($left, $top);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(100, 7, $this->text(mb_strtoupper($company->company_name)));

        $pdf->SetFont('times', 'B', 9);
        $pdf->Cell(35, 7, 'Voucher No :', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 10);
        $pdf->Cell(45, 7, $this->text($item->voucher_no ?: 'DRAFT'), 'B', 1, 'C');

        $pdf->SetXY($left, $top + 7);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell($width, 4, $copyLabel, 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);

        // ---------- Box ----------
        $boxTop = $top + 14;
        $pdf->Rect($left, $boxTop, $width, $boxH);

        $pdf->SetDrawColor(170, 170, 170);
        for ($i = 1; $i <= 3; $i++) {
            $pdf->Line($left, $boxTop + ($rowH * $i), $left + $width, $boxTop + ($rowH * $i));
        }
        $pdf->SetDrawColor(0, 0, 0);

        // Row 1: Transaction Type | Date
        $pdf->SetXY($left, $boxTop);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(40, $rowH, 'Transaction Type :', 0, 0, 'R');
        $pdf->Cell(5, $rowH, '');
        $pdf->SetFont('times', 'B', 10);
        $pdf->Cell(85, $rowH, 'Professional Fee');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, $rowH, 'Date :', 0, 0, 'R');

        $pdf->SetXY($left + 148, $boxTop + 1.5);
        $pdf->Cell(29, 7, $payroll->pay_date->format('M d, Y'), 'B', 0, 'C');

        // Row 2: Amount
        $pdf->SetXY($left, $boxTop + $rowH);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(40, $rowH, 'Amount :', 0, 0, 'R');
        $pdf->Cell(5, $rowH, '');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(130, $rowH, $amount);

        // Row 3: Payee
        $pdf->SetXY($left, $boxTop + ($rowH * 2));
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(40, $rowH, 'Payee :', 0, 0, 'R');
        $pdf->Cell(5, $rowH, '');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(130, $rowH, $this->text(mb_strtoupper($item->employee_name)));

        // ---------- Breakdown ----------
        $labelX = $left + 45;
        $labelW = 85;
        $amountW = 45;
        $y = $boxTop + ($rowH * 3) + 1;

        // Payroll Cutoff
        $pdf->SetXY($left, $y);
        $pdf->SetFont('helvetica', '', $fontSize);
        $pdf->Cell(40, $lineH, 'Payroll Cutoff :', 0, 0, 'R');
        $pdf->Cell(5, $lineH, '');
        $pdf->SetFont('helvetica', 'B', $fontSize);
        $pdf->Cell(130, $lineH, $payroll->period_start->format('M d').' - '.$payroll->period_end->format('M d, Y'));
        $y += $lineH;

        // Gross Pay
        $pdf->SetXY($labelX, $y);
        $pdf->SetFont('helvetica', 'B', $fontSize);
        $pdf->Cell($labelW, $lineH, 'Gross Pay');
        $pdf->Cell($amountW, $lineH, $this->money($item->gross_pay), 0, 0, 'R');
        $y += $lineH;

        // Deductions
        foreach ($deductions as $index => $deduction) {
            $label = $index === 0 ? 'Less: '.$deduction['label'] : $deduction['label'];

            $pdf->SetXY($labelX, $y);
            $pdf->SetFont('helvetica', '', $fontSize);
            $pdf->Cell($labelW, $lineH, $this->text($label));
            $pdf->Cell($amountW, $lineH, $this->money($deduction['amount']), 0, 0, 'R');
            $y += $lineH;
        }

        // Total Deductions
        if ($deductions !== []) {
            $pdf->SetDrawColor(170, 170, 170);
            $pdf->Line($labelX, $y, $labelX + $labelW + $amountW, $y);
            $pdf->SetDrawColor(0, 0, 0);

            $pdf->SetXY($labelX, $y);
            $pdf->SetFont('helvetica', 'B', $fontSize);
            $pdf->Cell($labelW, $lineH, 'Total Deductions');
            $pdf->Cell($amountW, $lineH, $this->money($item->total_deductions), 0, 0, 'R');
            $y += $lineH;
        }

        // ---------- NET PAY (walang background, makapal na linya lang) ----------
        $y += 1;
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);

        $pdf->SetXY($labelX, $y);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($labelW, $netH, 'NET PAY', 'TB', 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($amountW, $netH, $this->money($item->net_pay), 'TB', 0, 'R');

        $pdf->SetLineWidth(0.2);

        // ---------- Acknowledgment ----------
        $ackY = $boxTop + $boxH + 5;

        $pdf->SetXY($left + 3, $ackY);
        $pdf->SetFont('helvetica', '', 7);
        $prefix = 'I hereby acknowledge to receive the amount of ';
        $prefixW = $pdf->GetStringWidth($prefix) + 1;
        $pdf->Cell($prefixW, 5, $prefix);

        $pdf->SetFont('helvetica', 'BU', 7);
        $pdf->Cell(40, 5, $amount);

        // ---------- Signature ----------
        $sigX = $left + 118;
        $sigY = $ackY + 1;

        $pdf->SetXY($sigX, $sigY);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell(62, 6, $this->text(mb_strtoupper($item->employee_name)), 'B', 1, 'C');

        $pdf->SetX($sigX);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->Cell(62, 4, 'Signature & Date', 0, 1, 'C');
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
