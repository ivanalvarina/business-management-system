<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('update', $employee) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')->where(fn (Builder $query) => $query->where('company_id', $this->integer('company_id')))->ignore($employee),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'hire_date' => ['required', 'date'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(Employee::employmentTypes())],
            'pay_frequency' => ['required', Rule::in(Employee::payFrequencies())],
            'basic_salary' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'tin' => ['nullable', 'string', 'max:50'],
            'sss_number' => ['nullable', 'string', 'max:50'],
            'philhealth_number' => ['nullable', 'string', 'max:50'],
            'pagibig_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Employee::statuses())],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('company_id')) {
                    return;
                }

                $company = Company::find($this->integer('company_id'));

                if (! $company || ! $this->user()?->canAccessCompany($company)) {
                    $validator->errors()->add('company_id', __('You may only assign employees to companies you can access.'));
                }
            },
        ];
    }
}
