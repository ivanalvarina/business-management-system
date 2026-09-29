<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\ProductService;
use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientPurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $clientPurchaseOrder = $this->route('client_purchase_order');

        return $clientPurchaseOrder instanceof ClientPurchaseOrder
            && ($this->user()?->can('update', $clientPurchaseOrder) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ClientPurchaseOrder $clientPurchaseOrder */
        $clientPurchaseOrder = $this->route('client_purchase_order');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'quotation_ids' => ['nullable', 'array'],
            'quotation_ids.*' => ['integer', 'distinct', 'exists:quotations,id'],
            'client_po_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('client_purchase_orders', 'client_po_no')
                    ->where('company_id', $this->integer('company_id'))
                    ->where('client_id', $this->integer('client_id'))
                    ->ignore($clientPurchaseOrder),
            ],
            'po_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'status' => ['required', Rule::in(ClientPurchaseOrder::statuses())],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'items.*.quotation_item_id' => ['nullable', 'integer', 'exists:quotation_items,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999999.9999'],
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
                $this->validateQuotationLink($validator);
                $this->validateNotFulfilled($validator);
                $this->validateEditableStatus($validator);
                $this->validateItems($validator);
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
            $validator->errors()->add('company_id', __('You may only record client purchase orders for companies you can access.'));
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

    private function validateQuotationLink(Validator $validator): void
    {
        $quotationIds = collect((array) $this->input('quotation_ids', []))
            ->filter()
            ->map(fn (mixed $quotationId): int => (int) $quotationId)
            ->unique()
            ->values();
        $linkedItemIds = collect((array) $this->input('items', []))
            ->pluck('quotation_item_id')
            ->filter()
            ->map(fn (mixed $quotationItemId): int => (int) $quotationItemId)
            ->unique()
            ->values();

        if ($quotationIds->isEmpty()) {
            if ($linkedItemIds->isNotEmpty()) {
                $validator->errors()->add('quotation_ids', __('Select the approved quotations for linked quotation items.'));
            }

            return;
        }

        if ($validator->errors()->hasAny(['company_id', 'client_id', 'quotation_ids'])) {
            return;
        }

        $quotations = Quotation::query()
            ->with(['company', 'items:id,quotation_id'])
            ->whereIn('id', $quotationIds)
            ->get();

        if ($quotations->count() !== $quotationIds->count()) {
            $validator->errors()->add('quotation_ids', __('One or more selected quotations are not accessible.'));

            return;
        }

        foreach ($quotations as $quotation) {
            if (! $this->user()?->canAccessCompany($quotation->company)) {
                $validator->errors()->add('quotation_ids', __('One or more selected quotations are not accessible.'));
            }

            if ($quotation->status !== Quotation::STATUS_APPROVED) {
                $validator->errors()->add('quotation_ids', __('Only approved quotations can be linked to client purchase orders.'));
            }

            if ((int) $quotation->company_id !== $this->integer('company_id') || (int) $quotation->client_id !== $this->integer('client_id')) {
                $validator->errors()->add('quotation_ids', __('Selected quotations must belong to the selected company and client.'));
            }
        }

        if ($linkedItemIds->isEmpty()) {
            return;
        }

        $allowedItemIds = $quotations->flatMap->items->pluck('id');

        foreach ((array) $this->input('items', []) as $index => $item) {
            $linkedItemId = (int) ($item['quotation_item_id'] ?? 0);

            if ($linkedItemId > 0 && ! $allowedItemIds->contains($linkedItemId)) {
                $validator->errors()->add("items.{$index}.quotation_item_id", __('The linked quotation item does not belong to the selected quotations.'));
            }
        }
    }

    private function validateNotFulfilled(Validator $validator): void
    {
        /** @var ClientPurchaseOrder $clientPurchaseOrder */
        $clientPurchaseOrder = $this->route('client_purchase_order');

        if ($clientPurchaseOrder->isFulfilled()) {
            $validator->errors()->add('status', __('Fulfilled client purchase orders cannot be edited.'));
        }
    }

    private function validateEditableStatus(Validator $validator): void
    {
        if ($validator->errors()->has('status')) {
            return;
        }

        if (in_array($this->string('status')->toString(), [ClientPurchaseOrder::STATUS_FULFILLED, ClientPurchaseOrder::STATUS_COMPLETED], true)) {
            $validator->errors()->add('status', __('Use the Client PO action buttons to fulfill or complete this record.'));
        }
    }

    private function validateItems(Validator $validator): void
    {
        if ($validator->errors()->has('items')) {
            return;
        }

        foreach ($this->input('items', []) as $index => $item) {
            $productServiceId = (int) ($item['product_service_id'] ?? 0);

            if ($productServiceId <= 0) {
                continue;
            }

            $isActive = ProductService::query()
                ->whereKey($productServiceId)
                ->where('status', ProductService::STATUS_ACTIVE)
                ->exists();

            if (! $isActive) {
                $validator->errors()->add("items.{$index}.product_service_id", __('The selected product or service is not active.'));
            }
        }
    }
}
