<?php

namespace App\Support;

use App\Models\Payroll;
use App\Models\PayrollItem;

class PayrollRegisterPdfRenderer
{
    private const LEFT_MARGIN = 8.0;

    private const ROW_HEIGHT = 7.5;

    private const GROUP_HEIGHT = 5.0;

    private const HEADER_HEIGHT = 9.0;

    private const BOTTOM_LIMIT = 203.0;

    private const SUMMARY_LIMIT = 210.0;

    public function render(Payroll $payroll): string
    {
        $payroll->loadMissing(['company', 'items']);

        $pdf = new PdfCanvas('L', 'mm', 'Legal');
        $pdf->SetMargins(self::LEFT_MARGIN, 8, self::LEFT_MARGIN);
        $pdf->SetAutoPageBreak(false);
        $this->addPage($pdf, $payroll);
        $totals = array_fill(0, count($this->columns()), 0.0);

        if ($payroll->items->isEmpty()) {
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell($this->tableWidth(), 12, 'No employees in this payroll run.', 'B', 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $this->writeSectionBand($pdf, $payroll);
        }

        foreach ($payroll->items as $index => $item) {
            if ($pdf->GetY() + self::ROW_HEIGHT > self::BOTTOM_LIMIT) {
                $this->addPage($pdf, $payroll);
            }

            $values = $this->itemValues($item);
            $this->writeRow($pdf, $values, $index % 2 === 1);

            foreach ($values as $columnIndex => $value) {
                if ($columnIndex > 0) {
                    $totals[$columnIndex] += (float) $value;
                }
            }
        }

        if ($payroll->items->isNotEmpty()) {
            if ($pdf->GetY() + 12 > self::BOTTOM_LIMIT) {
                $this->addPage($pdf, $payroll);
            }

            $totals[0] = 'TOTAL';
            $this->writeTotals($pdf, $totals);
        }

        if ($pdf->GetY() + 38 > self::SUMMARY_LIMIT) {
            $this->addPage($pdf, $payroll, false);
        }

        $this->writeSummary($pdf, $payroll);

        return $pdf->Output('payroll-register.pdf', 'S');
    }

    private function addPage(PdfCanvas $pdf, Payroll $payroll, bool $withTableHeader = true): void
    {
        $pdf->AddPage();
        $pdf->SetDrawColor(25, 25, 25);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        // Title row
        $pdf->SetXY(self::LEFT_MARGIN, 8);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(210, 7, $this->text(mb_strtoupper($payroll->company->company_name)));
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell($this->tableWidth() - 210, 7, 'PAYROLL REGISTER', 0, 1, 'R');

        // Meta row
        $pdf->SetX(self::LEFT_MARGIN);
        $period = $payroll->period_start->format('F d').' - '.$payroll->period_end->format('d, Y');
        $this->meta($pdf, 'Cutoff:', $period, 150);
        $this->meta($pdf, 'Pay date:', $payroll->pay_date->format('F d, Y'), 85);
        $this->meta($pdf, 'Group:', ucfirst($payroll->payroll_group), 55);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell($this->tableWidth() - 290, 5, 'Page '.$pdf->PageNo(), 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);

        // Divider under the heading
        $lineY = $pdf->GetY() + 1;
        $pdf->SetLineWidth(0.5);
        $pdf->Line(self::LEFT_MARGIN, $lineY, self::LEFT_MARGIN + $this->tableWidth(), $lineY);
        $pdf->SetLineWidth(0.2);
        $pdf->SetY($lineY + 2);

        // Draft tag
        if ($payroll->isDraft()) {
            $pdf->SetDrawColor(180, 55, 55);
            $pdf->SetTextColor(180, 55, 55);
            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->SetX(self::LEFT_MARGIN + $this->tableWidth() - 38);
            $pdf->Cell(38, 4.5, 'DRAFT - FOR REVIEW', 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(25, 25, 25);
        } else {
            $pdf->Ln(4.5);
        }

        $pdf->Ln(2);

        if ($withTableHeader) {
            $this->writeTableHeader($pdf);
        }
    }

    private function meta(PdfCanvas $pdf, string $label, string $value, float $width): void
    {
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(110, 110, 110);
        $labelWidth = $pdf->GetStringWidth($label.' ') + 1;
        $pdf->Cell($labelWidth, 5, $label);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($width - $labelWidth, 5, $value);
    }

    private function writeTableHeader(PdfCanvas $pdf): void
    {
        $y = $pdf->GetY();

        // Column group labels (EARNINGS, ATTENDANCE, ...)
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetDrawColor(130, 130, 130);
        $pdf->SetLineWidth(0.2);

        foreach ($this->groups() as [$label, $start, $end]) {
            if ($label === '') {
                continue;
            }

            $x = $this->columnX($start);
            $w = $this->spanWidth($start, $end);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, self::GROUP_HEIGHT, $label, 0, 0, 'C');
            $pdf->Line($x + 1.5, $y + self::GROUP_HEIGHT, $x + $w - 1.5, $y + self::GROUP_HEIGHT);
        }

        $y += self::GROUP_HEIGHT + 0.8;

        // Column labels
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Rect(self::LEFT_MARGIN, $y, $this->tableWidth(), self::HEADER_HEIGHT, 'F');
        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetTextColor(0, 0, 0);

        foreach ($this->columns() as $index => $column) {
            $pdf->SetXY($this->columnX($index), $y);
            $pdf->MultiCell($column['width'], 4.5, $column['label'], 0, $column['align']);
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(self::LEFT_MARGIN, $y, self::LEFT_MARGIN + $this->tableWidth(), $y);
        $pdf->Line(self::LEFT_MARGIN, $y + self::HEADER_HEIGHT, self::LEFT_MARGIN + $this->tableWidth(), $y + self::HEADER_HEIGHT);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(25, 25, 25);

        $pdf->SetXY(self::LEFT_MARGIN, $y + self::HEADER_HEIGHT);
    }

    private function writeSectionBand(PdfCanvas $pdf, Payroll $payroll): void
    {
        $count = $payroll->items->count();
        $label = mb_strtoupper((string) $payroll->payroll_group).' PAYROLL   |   '
            .$count.' '.($count === 1 ? 'employee' : 'employees');

        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetFont('helvetica', 'B', 6.8);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX(self::LEFT_MARGIN);
        $pdf->Cell($this->tableWidth(), 6, $this->text($label), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
    }

    /** @param array<int, string|float> $values */
    private function writeRow(PdfCanvas $pdf, array $values, bool $fill): void
    {
        $columns = $this->columns();
        $y = $pdf->GetY();
        $lastIndex = array_key_last($values);

        if ($fill) {
            $pdf->SetFillColor(248, 248, 248);
            $pdf->Rect(self::LEFT_MARGIN, $y, $this->tableWidth(), self::ROW_HEIGHT, 'F');
        }

        foreach ($values as $index => $value) {
            $column = $columns[$index];
            $pdf->SetXY($this->columnX($index), $y);

            if ($index === 0) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', '', 6.6);
                $display = $this->fitText($pdf, (string) $value, $column['width'] - 2);
            } else {
                $amount = (float) $value;
                $isZero = abs($amount) < 0.005;
                $isDeduction = $column['deduction'] && $amount > 0;

                if ($isZero) {
                    $pdf->SetTextColor(175, 175, 175);
                } elseif ($isDeduction || $amount < 0) {
                    $pdf->SetTextColor(185, 28, 28);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }

                $pdf->SetFont('helvetica', $index === $lastIndex ? 'B' : '', $index === $lastIndex ? 6.8 : 6.4);
                $display = $this->formatValue($amount, $column);
            }

            $pdf->Cell($column['width'], self::ROW_HEIGHT, $display, 0, 0, $column['align']);
        }

        // Hairline under the row + soft column group separators
        $pdf->SetDrawColor(222, 222, 222);
        $pdf->SetLineWidth(0.15);
        $pdf->Line(self::LEFT_MARGIN, $y + self::ROW_HEIGHT, self::LEFT_MARGIN + $this->tableWidth(), $y + self::ROW_HEIGHT);
        $this->drawSeparators($pdf, $y, self::ROW_HEIGHT);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(25, 25, 25);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(self::LEFT_MARGIN, $y + self::ROW_HEIGHT);
    }

    /** @param array<int, string|float> $totals */
    private function writeTotals(PdfCanvas $pdf, array $totals): void
    {
        $columns = $this->columns();
        $y = $pdf->GetY();
        $height = 8.5;

        $pdf->SetFillColor(240, 240, 240);
        $pdf->Rect(self::LEFT_MARGIN, $y, $this->tableWidth(), $height, 'F');

        foreach ($totals as $index => $value) {
            $column = $columns[$index];
            $pdf->SetXY($this->columnX($index), $y);
            $pdf->SetFont('helvetica', 'B', 6.8);

            if ($index === 0) {
                $pdf->SetTextColor(0, 0, 0);
                $display = (string) $value;
            } else {
                $amount = (float) $value;
                $isDeduction = $column['deduction'] && $amount > 0;
                $pdf->SetTextColor(...($isDeduction ? [160, 20, 20] : [0, 0, 0]));
                $display = $this->formatValue($amount, $column);
            }

            $pdf->Cell($column['width'], $height, $display, 0, 0, $column['align']);
        }

        // Thick top line, double bottom line
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(self::LEFT_MARGIN, $y, self::LEFT_MARGIN + $this->tableWidth(), $y);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(self::LEFT_MARGIN, $y + $height, self::LEFT_MARGIN + $this->tableWidth(), $y + $height);
        $pdf->Line(self::LEFT_MARGIN, $y + $height + 0.7, self::LEFT_MARGIN + $this->tableWidth(), $y + $height + 0.7);
        $pdf->SetDrawColor(25, 25, 25);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(self::LEFT_MARGIN, $y + $height + 6);
    }

    private function writeSummary(PdfCanvas $pdf, Payroll $payroll): void
    {
        $top = $pdf->GetY();

        // ---------- Signatures (left) ----------
        $sigY = $top + 16;
        $sigX = self::LEFT_MARGIN;

        foreach (['Prepared by', 'Checked by', 'Approved by'] as $label) {
            $pdf->SetDrawColor(80, 80, 80);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($sigX, $sigY, $sigX + 60, $sigY);
            $pdf->SetXY($sigX, $sigY + 1);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetTextColor(110, 110, 110);
            $pdf->Cell(60, 4, $label.' / Date', 0, 0, 'C');
            $sigX += 75;
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(25, 25, 25);

        // ---------- Summary (right, stacked) ----------
        $labelWidth = 45;
        $valueWidth = 38;
        $x = self::LEFT_MARGIN + $this->tableWidth() - ($labelWidth + $valueWidth);
        $y = $top;
        $rowH = 6;

        $rows = [
            ['Gross', (float) $payroll->items->sum('gross_pay'), false],
            ['Less: Deductions', (float) $payroll->items->sum('total_deductions'), true],
            ['Allowance', (float) $payroll->items->sum('allowance'), false],
        ];

        foreach ($rows as [$label, $value, $isDeduction]) {
            $pdf->SetXY($x, $y);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell($labelWidth, $rowH, $label, 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetTextColor(...($isDeduction && $value > 0 ? [160, 20, 20] : [0, 0, 0]));
            $pdf->Cell($valueWidth, $rowH, $this->number($value, $isDeduction), 0, 0, 'R');

            $pdf->SetDrawColor(222, 222, 222);
            $pdf->SetLineWidth(0.15);
            $pdf->Line($x, $y + $rowH, $x + $labelWidth + $valueWidth, $y + $rowH);
            $y += $rowH;
        }

        // Net payroll
        $netH = 8;
        $pdf->SetXY($x, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($labelWidth, $netH, 'NET PAYROLL', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($valueWidth, $netH, $this->number((float) $payroll->items->sum('net_pay')), 0, 0, 'R');

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($x, $y, $x + $labelWidth + $valueWidth, $y);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y + $netH, $x + $labelWidth + $valueWidth, $y + $netH);
        $pdf->Line($x, $y + $netH + 0.7, $x + $labelWidth + $valueWidth, $y + $netH + 0.7);

        $pdf->SetDrawColor(25, 25, 25);
        $pdf->SetTextColor(0, 0, 0);
    }

    private function drawSeparators(PdfCanvas $pdf, float $y, float $height): void
    {
        $lastIndex = count($this->columns()) - 1;

        foreach ($this->groups() as [, , $end]) {
            if ($end >= $lastIndex) {
                continue;
            }

            $x = $this->columnX($end + 1);
            $pdf->Line($x, $y, $x, $y + $height);
        }
    }

    /** @return array<int, string|float> */
    private function itemValues(PayrollItem $item): array
    {
        return [
            $item->employee_name,
            (float) $item->period_basic_pay,
            (float) $item->regular_ot_pay,
            (float) $item->special_holiday_pay + (float) $item->special_holiday_ot_pay,
            (float) $item->regular_holiday_pay + (float) $item->regular_holiday_ot_pay,
            (float) $item->late_hours,
            (float) $item->late_deduction,
            (float) $item->absent_days,
            (float) $item->absence_deduction,
            (float) $item->salary_adjustment,
            (float) $item->gross_pay,
            (float) $item->sss_contribution,
            (float) $item->philhealth_contribution,
            (float) $item->pagibig_contribution,
            (float) $item->withholding_tax,
            (float) $item->other_deduction,
            (float) $item->allowance,
            (float) $item->net_pay,
        ];
    }

    /** @return array<int, array{0: string, 1: int, 2: int}> */
    private function groups(): array
    {
        return [
            ['', 0, 0],
            ['EARNINGS', 1, 4],
            ['ATTENDANCE', 5, 8],
            ['GROSS', 9, 10],
            ['DEDUCTIONS', 11, 15],
            ['', 16, 16],
            ['', 17, 17],
        ];
    }

    /** @return array<int, array{label: string, width: float, align: string, deduction: bool, decimals?: int}> */
    private function columns(): array
    {
        return [
            ['label' => "EMPLOYEE\n ", 'width' => 46, 'align' => 'L', 'deduction' => false],
            ['label' => "BASIC\nPAY", 'width' => 21, 'align' => 'R', 'deduction' => false],
            ['label' => "REG. OT\nPAY", 'width' => 18, 'align' => 'R', 'deduction' => false],
            ['label' => "130%\nHOL./OT", 'width' => 18, 'align' => 'R', 'deduction' => false],
            ['label' => "200%\nHOL./OT", 'width' => 18, 'align' => 'R', 'deduction' => false],
            ['label' => "LATE\nHRS", 'width' => 11, 'align' => 'R', 'deduction' => false, 'decimals' => 2],
            ['label' => "LATE\nDED.", 'width' => 18, 'align' => 'R', 'deduction' => true],
            ['label' => "ABS.\nDAYS", 'width' => 11, 'align' => 'R', 'deduction' => false, 'decimals' => 1],
            ['label' => "ABSENT\nDED.", 'width' => 18, 'align' => 'R', 'deduction' => true],
            ['label' => "SALARY\nADJ.", 'width' => 17, 'align' => 'R', 'deduction' => false],
            ['label' => "GROSS\nPAY", 'width' => 23, 'align' => 'R', 'deduction' => false],
            ['label' => "SSS\n ", 'width' => 16, 'align' => 'R', 'deduction' => true],
            ['label' => "PHIL-\nHEALTH", 'width' => 16, 'align' => 'R', 'deduction' => true],
            ['label' => "PAG-\nIBIG", 'width' => 16, 'align' => 'R', 'deduction' => true],
            ['label' => "W/TAX\n ", 'width' => 16, 'align' => 'R', 'deduction' => true],
            ['label' => "OTHER\nDED.", 'width' => 16, 'align' => 'R', 'deduction' => true],
            ['label' => "ALLOW-\nANCE", 'width' => 16, 'align' => 'R', 'deduction' => false],
            ['label' => "NET\nPAY", 'width' => 24, 'align' => 'R', 'deduction' => false],
        ];
    }

    private function tableWidth(): float
    {
        return (float) collect($this->columns())->sum('width');
    }

    private function columnX(int $index): float
    {
        $x = self::LEFT_MARGIN;

        foreach (array_slice($this->columns(), 0, $index) as $column) {
            $x += $column['width'];
        }

        return $x;
    }

    private function spanWidth(int $start, int $end): float
    {
        return (float) collect(array_slice($this->columns(), $start, $end - $start + 1))->sum('width');
    }

    /** @param array{deduction: bool, decimals?: int} $column */
    private function formatValue(float $value, array $column): string
    {
        if (abs($value) < 0.005) {
            return '-';
        }

        $formatted = number_format(abs($value), $column['decimals'] ?? 2);

        if (($column['deduction'] && $value > 0) || $value < 0) {
            return '('.$formatted.')';
        }

        return $formatted;
    }

    private function number(float $value, bool $deduction = false): string
    {
        $formatted = number_format(abs($value), 2);

        if ($deduction && $value > 0) {
            return '('.$formatted.')';
        }

        return $value < 0 ? '('.$formatted.')' : $formatted;
    }

    private function fitText(PdfCanvas $pdf, string $value, float $maxWidth): string
    {
        $value = mb_strtoupper($value);
        $displayValue = $this->text($value);
        $isTruncated = false;

        while ($pdf->GetStringWidth($displayValue.($isTruncated ? '...' : '')) > $maxWidth && mb_strlen($value) > 3) {
            $value = mb_substr($value, 0, -1);
            $displayValue = $this->text($value);
            $isTruncated = true;
        }

        return rtrim($displayValue).($isTruncated ? '...' : '');
    }

    private function text(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return $converted === false ? $value : $converted;
    }
}