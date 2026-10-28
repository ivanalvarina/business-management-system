<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Payroll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            && ($this->user()?->can('create', Payroll::class) ?? false)
            && ($this->user()?->canAccessCompany($company) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('payroll_holidays', 'date')->where('company_id', $this->route('company')->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
