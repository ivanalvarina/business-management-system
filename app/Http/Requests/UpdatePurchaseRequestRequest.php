<?php

namespace App\Http\Requests;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePurchaseRequestRequest extends StorePurchaseRequestRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchase_request');

        return $purchaseRequest instanceof PurchaseRequest
            && ($this->user()?->can('update', $purchaseRequest) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
}
