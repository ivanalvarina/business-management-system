<?php

namespace App\Models;

use Database\Factories\ClientPurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_purchase_order_id
 * @property int|null $product_service_id
 * @property int|null $quotation_item_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $discount
 * @property string $tax
 * @property string $line_total
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['client_purchase_order_id', 'product_service_id', 'quotation_item_id', 'description', 'quantity', 'unit', 'unit_price', 'discount', 'tax', 'line_total', 'sort_order'])]
class ClientPurchaseOrderItem extends Model
{
    /** @use HasFactory<ClientPurchaseOrderItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ClientPurchaseOrder, $this>
     */
    public function clientPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ClientPurchaseOrder::class);
    }

    /**
     * @return BelongsTo<ProductService, $this>
     */
    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
