<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVendorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_code' => ['required', 'string', 'max:50', 'unique:vendors,vendor_code'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'vendor_category_id' => ['nullable', 'integer', 'exists:vendor_categories,id'],
            'new_category_name' => ['nullable', 'string', 'max:255', 'unique:vendor_categories,name'],
            'status' => ['required', Rule::in([Vendor::STATUS_ACTIVE, Vendor::STATUS_INACTIVE])],
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['integer', 'distinct', 'exists:companies,id'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.department' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:255'],
            'contacts.*.mobile' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('vendor_category_id') && ! $this->filled('new_category_name')) {
                    $validator->errors()->add('vendor_category_id', __('Select a vendor category or enter a new one.'));
                }

                $companyIds = array_values(array_unique(array_map(
                    fn (mixed $companyId): int => (int) $companyId,
                    (array) $this->input('company_ids', []),
                )));

                if ($companyIds === [] || $this->user()?->hasGlobalCompanyAccess()) {
                    return;
                }

                $authorizedCount = Company::query()
                    ->whereIn('id', $companyIds)
                    ->whereHas('users', fn ($query) => $query->whereKey($this->user()->id))
                    ->count();

                if ($authorizedCount !== count($companyIds)) {
                    $validator->errors()->add('company_ids', __('You may only assign vendors to companies you can access.'));
                }
            },
        ];
    }
}
