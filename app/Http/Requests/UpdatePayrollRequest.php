<?php

namespace App\Http\Requests;

use App\Models\Payroll;
use App\Support\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePayrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $payroll = $this->route('payroll');

        return $payroll instanceof Payroll
            && ($this->user()?->can('update', $payroll) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.days_worked' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.absent_days' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.late_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.regular_ot_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.special_holiday_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.regular_holiday_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.salary_adjustment' => ['required', 'numeric', 'decimal:0,2', 'min:-9999999999999.99', 'max:9999999999999.99'],
            'items.*.sss_contribution' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.philhealth_contribution' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.pagibig_contribution' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.withholding_tax' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.other_deduction' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.allowance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Payroll $payroll */
            $payroll = $this->route('payroll');
            $expectedIds = $payroll->items()->pluck('id')->sort()->values()->all();
            $submittedItems = $this->input('items', []);
            $submittedIds = collect($submittedItems)->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

            if ($expectedIds !== $submittedIds) {
                $validator->errors()->add('items', __('The payroll employee list has changed. Refresh and try again.'));

                return;
            }

            $weekdays = PayrollPeriod::weekdays($payroll->period_start->toImmutable(), $payroll->period_end->toImmutable());
            $itemsById = $payroll->items()->get(['id', 'payroll_days_basis'])->keyBy('id');

            foreach ($submittedItems as $index => $item) {
                $payrollDaysBasis = (float) $itemsById->get((int) $item['id'])->payroll_days_basis;

                if ((float) $item['days_worked'] > $payrollDaysBasis) {
                    $validator->errors()->add("items.{$index}.days_worked", __('Worked days cannot exceed the :days-day payroll basis.', ['days' => $payrollDaysBasis]));
                }

                if ((float) $item['absent_days'] > $weekdays) {
                    $validator->errors()->add("items.{$index}.absent_days", __('Absent days cannot exceed :days scheduled weekdays.', ['days' => $weekdays]));
                }
            }
        }];
    }
}
