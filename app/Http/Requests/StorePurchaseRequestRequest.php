<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'request_date' => ['required', 'date'],
            'date_required' => ['nullable', 'date', 'after_or_equal:request_date'],
            'client_project' => ['nullable', 'string', 'max:255'],
            'requested_by_name' => ['nullable', 'string', 'max:255'],
            'checked_by_name' => ['nullable', 'string', 'max:255'],
            'noted_by_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999999.9999'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
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
                $this->validateVendorCompany($validator);
                $this->validateActiveProducts($validator);
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
            $validator->errors()->add('company_id', __('You may only create purchase requests for companies you can access.'));
        }
    }

    private function validateVendorCompany(Validator $validator): void
    {
        if ($this->input('vendor_id') === null || $validator->errors()->hasAny(['company_id', 'vendor_id'])) {
            return;
        }

        $vendorBelongsToCompany = Vendor::query()
            ->whereKey($this->integer('vendor_id'))
            ->where('vendors.status', Vendor::STATUS_ACTIVE)
            ->whereHas('companies', fn ($query) => $query
                ->whereKey($this->integer('company_id'))
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_vendor.status', Company::STATUS_ACTIVE))
            ->exists();

        if (! $vendorBelongsToCompany) {
            $validator->errors()->add('vendor_id', __('The selected vendor is not active for the selected company.'));
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

        foreach ((array) $this->input('items', []) as $index => $item) {
            if ($inactiveProductIds->contains((int) ($item['product_service_id'] ?? 0))) {
                $validator->errors()->add("items.{$index}.product_service_id", __('Only active products or services can be selected.'));
            }
        }
    }
}
