<?php

namespace App\Models;

use Database\Factories\ReceivingReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['receiving_receipt_id', 'purchase_order_item_id', 'quantity', 'description', 'sort_order'])]
class ReceivingReceiptItem extends Model
{
    /** @use HasFactory<ReceivingReceiptItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ReceivingReceipt, $this>
     */
    public function receivingReceipt(): BelongsTo
    {
        return $this->belongsTo(ReceivingReceipt::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }
}
