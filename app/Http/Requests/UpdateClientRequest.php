<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('update', $client) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'client_code' => ['required', 'string', 'max:50', Rule::unique('clients', 'client_code')->ignore($client)],
            'client_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
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
                    $validator->errors()->add('company_ids', __('You may only assign clients to companies you can access.'));
                }
            },
        ];
    }
}
