<?php

namespace App\Support;

use App\Models\PayrollItem;

class PayrollVoucherBreakdown
{
    /** @return array<int, array{label: string, amount: float}> */
    public function deductions(PayrollItem $item): array
    {
        $deductions = collect([
            ['label' => 'Absence', 'amount' => (float) $item->absence_deduction],
            ['label' => 'Late', 'amount' => (float) $item->late_deduction],
            ['label' => 'SSS', 'amount' => (float) $item->sss_contribution],
            ['label' => 'PhilHealth', 'amount' => (float) $item->philhealth_contribution],
            ['label' => 'Pag-IBIG', 'amount' => (float) $item->pagibig_contribution],
            ['label' => 'Withholding tax', 'amount' => (float) $item->withholding_tax],
        ]);

        $itemizedDeductions = collect($item->itemizedOtherDeductions())
            ->groupBy('type')
            ->map(fn ($rows, string $type): array => [
                'label' => PayrollItem::OTHER_DEDUCTION_LABELS[$type] ?? 'Other',
                'amount' => (float) collect($rows)->sum('amount'),
            ]);

        if ($itemizedDeductions->isEmpty() && (float) $item->other_deduction > 0) {
            $itemizedDeductions->push([
                'label' => 'Other deduction',
                'amount' => (float) $item->other_deduction,
            ]);
        }

        return $deductions->concat($itemizedDeductions)
            ->filter(fn (array $deduction): bool => $deduction['amount'] > 0)
            ->values()
            ->all();
    }
}
