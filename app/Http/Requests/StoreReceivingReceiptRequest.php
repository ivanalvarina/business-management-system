<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReceivingReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReceivingReceipt::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'received_date' => ['required', 'date'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'checked_by_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999999.9999'],
            'items.*.description' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePurchaseOrderAccess($validator);
                $this->validatePurchaseOrderItems($validator);
            },
        ];
    }

    private function validatePurchaseOrderAccess(Validator $validator): void
    {
        if ($validator->errors()->has('purchase_order_id')) {
            return;
        }

        $purchaseOrder = PurchaseOrder::with('company')->find($this->integer('purchase_order_id'));

        if (! $purchaseOrder || ! $this->user()?->canAccessCompany($purchaseOrder->company)) {
            $validator->errors()->add('purchase_order_id', __('You may only receive purchase orders for companies you can access.'));

            return;
        }

        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_COMPLETED], true)) {
            $validator->errors()->add('purchase_order_id', __('Only approved, sent, or completed purchase orders can be received.'));
        }
    }

    private function validatePurchaseOrderItems(Validator $validator): void
    {
        if ($validator->errors()->has('purchase_order_id')) {
            return;
        }

        $itemIds = collect((array) $this->input('items', []))
            ->pluck('purchase_order_item_id')
            ->filter()
            ->map(fn (mixed $itemId): int => (int) $itemId)
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return;
        }

        $validItemIds = PurchaseOrderItem::query()
            ->whereIn('id', $itemIds)
            ->where('purchase_order_id', $this->integer('purchase_order_id'))
            ->pluck('id')
            ->all();

        $invalidItemIds = $itemIds->diff($validItemIds);

        foreach ((array) $this->input('items', []) as $index => $item) {
            if ($invalidItemIds->contains((int) ($item['purchase_order_item_id'] ?? 0))) {
                $validator->errors()->add("items.{$index}.purchase_order_item_id", __('The selected item does not belong to the purchase order.'));
            }
        }
    }
}
