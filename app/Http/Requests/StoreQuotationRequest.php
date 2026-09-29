<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Company;
use App\Models\ProductService;
use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quotation::class) ?? false;
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999999.9999'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.tax' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompanyAccess($validator);
                $this->validateClientCompany($validator);
                $this->validateActiveProducts($validator);
                $this->validateLineDiscounts($validator);
            },
        ];
    }

    private function validateCompanyAccess(Validator $validator): void
    {
        if ($validator->errors()->has('company_id')) {
            return;
        }

        $company = Company::find($this->integer('company_id'));

        if (! $company || ! $this->user()?->canAccessCompany($company)) {
            $validator->errors()->add('company_id', __('You may only create quotations for companies you can access.'));
        }
    }

    private function validateClientCompany(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['company_id', 'client_id'])) {
            return;
        }

        $clientBelongsToCompany = Client::query()
            ->whereKey($this->integer('client_id'))
            ->where('clients.status', Client::STATUS_ACTIVE)
            ->whereHas('companies', fn ($query) => $query
                ->whereKey($this->integer('company_id'))
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_client.status', Company::STATUS_ACTIVE))
            ->exists();

        if (! $clientBelongsToCompany) {
            $validator->errors()->add('client_id', __('The selected client is not active for the selected company.'));
        }
    }

    private function validateActiveProducts(Validator $validator): void
    {
        $productIds = collect((array) $this->input('items', []))
            ->pluck('product_service_id')
            ->filter()
            ->map(fn (mixed $productId): int => (int) $productId)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $activeProductIds = ProductService::query()
            ->whereIn('id', $productIds)
            ->where('status', ProductService::STATUS_ACTIVE)
            ->pluck('id')
            ->all();

        $inactiveProductIds = $productIds->diff($activeProductIds);

        if ($inactiveProductIds->isEmpty()) {
            return;
        }

        foreach ((array) $this->input('items', []) as $index => $item) {
            if ($inactiveProductIds->contains((int) ($item['product_service_id'] ?? 0))) {
                $validator->errors()->add("items.{$index}.product_service_id", __('Only active products or services can be selected.'));
            }
        }
    }

    private function validateLineDiscounts(Validator $validator): void
    {
        foreach ((array) $this->input('items', []) as $index => $item) {
            if ($validator->errors()->hasAny([
                "items.{$index}.quantity",
                "items.{$index}.unit_price",
                "items.{$index}.discount",
            ])) {
                continue;
            }

            $lineBase = round(((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0)), 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);

            if ($discount > $lineBase) {
                $validator->errors()->add("items.{$index}.discount", __('The line discount may not exceed the line subtotal.'));
            }
        }
    }
}
