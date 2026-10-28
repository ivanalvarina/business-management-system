<?php

namespace App\Models;

use Database\Factories\PayrollItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_id', 'employee_id', 'employee_number', 'employee_name', 'department', 'job_title', 'employment_type', 'voucher_no', 'pay_frequency', 'monthly_basic_salary', 'bank_name', 'bank_account_last_four', 'hours_per_day', 'payroll_days_basis', 'monthly_workdays_divisor', 'annual_workdays_divisor', 'regular_ot_multiplier', 'special_holiday_multiplier', 'special_holiday_ot_multiplier', 'regular_holiday_multiplier', 'regular_holiday_ot_multiplier', 'days_worked', 'absent_days', 'late_hours', 'regular_ot_hours', 'special_holiday_hours', 'special_holiday_ot_hours', 'regular_holiday_hours', 'regular_holiday_ot_hours', 'salary_adjustment', 'sss_contribution', 'philhealth_contribution', 'pagibig_contribution', 'withholding_tax', 'other_deduction', 'other_deductions', 'allowance', 'period_basic_pay', 'daily_rate', 'hourly_rate', 'absence_deduction', 'late_deduction', 'regular_ot_pay', 'special_holiday_pay', 'special_holiday_ot_pay', 'regular_holiday_pay', 'regular_holiday_ot_pay', 'gross_pay', 'total_deductions', 'net_pay'])]
class PayrollItem extends Model
{
    /** @use HasFactory<PayrollItemFactory> */
    use HasFactory;

    public const INPUT_FIELDS = ['days_worked', 'absent_days', 'late_hours', 'regular_ot_hours', 'special_holiday_hours', 'regular_holiday_hours', 'salary_adjustment', 'sss_contribution', 'philhealth_contribution', 'pagibig_contribution', 'withholding_tax', 'other_deduction', 'other_deductions', 'allowance'];

    public const OTHER_DEDUCTION_LABELS = [
        'cash_advance' => 'Cash advance',
        'company_loan' => 'Company loan',
        'equipment_accountability' => 'Equipment / accountability',
        'other' => 'Other',
    ];

    /** @return BelongsTo<Payroll, $this> */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array<int, array{type: string, amount: float, reference: string|null, remarks: string|null}>
     */
    public function itemizedOtherDeductions(): array
    {
        $deductions = $this->getAttribute('other_deductions');

        if (! is_array($deductions)) {
            return [];
        }

        $normalized = [];

        foreach ($deductions as $deduction) {
            if (! is_array($deduction)) {
                continue;
            }

            $type = $deduction['type'] ?? 'other';
            $amount = $deduction['amount'] ?? 0;
            $reference = $deduction['reference'] ?? null;
            $remarks = $deduction['remarks'] ?? null;

            $normalized[] = [
                'type' => is_string($type) ? $type : 'other',
                'amount' => is_numeric($amount) ? (float) $amount : 0.0,
                'reference' => is_string($reference) ? $reference : null,
                'remarks' => is_string($remarks) ? $remarks : null,
            ];
        }

        return $normalized;
    }

    protected function casts(): array
    {
        $decimals = array_diff($this->getFillable(), ['payroll_id', 'employee_id', 'employee_number', 'employee_name', 'department', 'job_title', 'employment_type', 'voucher_no', 'pay_frequency', 'bank_name', 'bank_account_last_four', 'other_deductions']);

        return [
            ...collect($decimals)->mapWithKeys(fn (string $attribute): array => [$attribute => 'decimal:2'])->all(),
            'other_deductions' => 'array',
        ];
    }
}
