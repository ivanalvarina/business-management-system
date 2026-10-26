<?php

namespace App\Support;

use App\Models\PayrollItem;

class PayrollVoucherBreakdown
{
    /** @return array<int, array{label: string, amount: float}> */
    public function deductions(PayrollItem $item): array
    {
        return collect([
            ['label' => 'Absence', 'amount' => (float) $item->absence_deduction],
            ['label' => 'Late', 'amount' => (float) $item->late_deduction],
            ['label' => 'SSS', 'amount' => (float) $item->sss_contribution],
            ['label' => 'PhilHealth', 'amount' => (float) $item->philhealth_contribution],
            ['label' => 'Pag-IBIG', 'amount' => (float) $item->pagibig_contribution],
            ['label' => 'Withholding tax', 'amount' => (float) $item->withholding_tax],
            ['label' => 'Other deduction', 'amount' => (float) $item->other_deduction],
        ])->filter(fn (array $deduction): bool => $deduction['amount'] > 0)
            ->values()
            ->all();
    }
}
