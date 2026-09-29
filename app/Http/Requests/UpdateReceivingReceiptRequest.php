<?php

namespace App\Http\Requests;

use App\Models\ReceivingReceipt;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateReceivingReceiptRequest extends StoreReceivingReceiptRequest
{
    public function authorize(): bool
    {
        $receivingReceipt = $this->route('receiving_receipt');

        return $receivingReceipt instanceof ReceivingReceipt
            && ($this->user()?->can('update', $receivingReceipt) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
}
