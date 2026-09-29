<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('update', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'purchase_request_ids' => ['nullable', 'array'],
            'purchase_request_ids.*' => ['integer', 'distinct', 'exists:purchase_requests,id'],
            'po_date' => ['required', 'date'],
            'expected_delivery' => ['nullable', 'date', 'after_or_equal:po_date'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'items.*.purchase_request_item_id' => ['nullable', 'integer', 'exists:purchase_request_items,id'],
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
                $this->validateVendorCompany($validator);
                $this->validatePurchaseRequestLink($validator);
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
            $validator->errors()->add('company_id', __('You may only create purchase orders for companies you can access.'));
        }
    }

    private function validateVendorCompany(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['company_id', 'vendor_id'])) {
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

        if ($inactiveProductIds->isEmpty()) {
            return;
        }

        foreach ((array) $this->input('items', []) as $index => $item) {
            if ($inactiveProductIds->contains((int) ($item['product_service_id'] ?? 0))) {
                $validator->errors()->add("items.{$index}.product_service_id", __('Only active products or services can be selected.'));
            }
        }
    }

    private function validatePurchaseRequestLink(Validator $validator): void
    {
        $purchaseRequestIds = collect((array) $this->input('purchase_request_ids', []))
            ->filter()
            ->map(fn (mixed $purchaseRequestId): int => (int) $purchaseRequestId)
            ->unique()
            ->values();
        $linkedItemIds = collect((array) $this->input('items', []))
            ->pluck('purchase_request_item_id')
            ->filter()
            ->map(fn (mixed $itemId): int => (int) $itemId)
            ->unique()
            ->values();

        if ($purchaseRequestIds->isEmpty()) {
            if ($linkedItemIds->isNotEmpty()) {
                $validator->errors()->add('purchase_request_ids', __('Select the approved purchase requests for linked PR items.'));
            }

            return;
        }

        if ($validator->errors()->hasAny(['company_id', 'vendor_id', 'purchase_request_ids'])) {
            return;
        }

        $purchaseRequests = PurchaseRequest::query()
            ->with(['company', 'items:id,purchase_request_id'])
            ->whereIn('id', $purchaseRequestIds)
            ->get();

        if ($purchaseRequests->count() !== $purchaseRequestIds->count()) {
            $validator->errors()->add('purchase_request_ids', __('One or more selected purchase requests are not accessible.'));

            return;
        }

        foreach ($purchaseRequests as $purchaseRequest) {
            if (! $this->user()?->canAccessCompany($purchaseRequest->company)) {
                $validator->errors()->add('purchase_request_ids', __('One or more selected purchase requests are not accessible.'));
            }

            if ($purchaseRequest->status !== PurchaseRequest::STATUS_APPROVED) {
                $validator->errors()->add('purchase_request_ids', __('Only approved purchase requests can be linked to purchase orders.'));
            }

            if ((int) $purchaseRequest->company_id !== $this->integer('company_id')) {
                $validator->errors()->add('purchase_request_ids', __('Selected purchase requests must belong to the selected company.'));
            }

            if ($purchaseRequest->vendor_id !== null && (int) $purchaseRequest->vendor_id !== $this->integer('vendor_id')) {
                $validator->errors()->add('purchase_request_ids', __('Selected purchase request vendors must match the purchase order vendor.'));
            }
        }

        if ($linkedItemIds->isEmpty()) {
            return;
        }

        $allowedItemIds = $purchaseRequests->flatMap->items->pluck('id');

        foreach ((array) $this->input('items', []) as $index => $item) {
            $linkedItemId = (int) ($item['purchase_request_item_id'] ?? 0);

            if ($linkedItemId > 0 && ! $allowedItemIds->contains($linkedItemId)) {
                $validator->errors()->add("items.{$index}.purchase_request_item_id", __('The linked PR item does not belong to the selected purchase request.'));
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
