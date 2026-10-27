<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['integer', 'distinct', Rule::exists('companies', 'id')->where('status', Company::STATUS_ACTIVE)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('company_ids') || $this->user()->hasGlobalCompanyAccess()) {
                return;
            }

            $companyIds = $this->array('company_ids');
            $accessibleCount = Company::query()
                ->active()
                ->whereKey($companyIds)
                ->whereHas('users', fn (Builder $query) => $query->whereKey($this->user()->id))
                ->count();

            if ($accessibleCount !== count($companyIds)) {
                $validator->errors()->add('company_ids', __('You may only assign users to companies you can access.'));
            }
        }];
    }
}
