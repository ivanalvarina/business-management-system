<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Payroll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payroll::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'payroll_group' => ['required', 'string', Rule::in(Payroll::creatableGroups())],
            'cutoff_type' => ['required', 'string', Rule::in(Payroll::cutoffTypes())],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('company_id')) {
                return;
            }

            $company = Company::find($this->integer('company_id'));

            if (! $company || ! $company->isActive() || ! $this->user()?->canAccessCompany($company)) {
                $validator->errors()->add('company_id', __('Select an active company you can access.'));
            }
        }];
    }
}
