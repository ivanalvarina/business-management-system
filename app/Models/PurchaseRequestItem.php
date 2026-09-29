<?php

namespace App\Models;

use Database\Factories\PurchaseRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'product_service_id', 'description', 'quantity', 'unit', 'unit_price', 'tax', 'line_total', 'sort_order'])]
class PurchaseRequestItem extends Model
{
    /** @use HasFactory<PurchaseRequestItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<ProductService, $this>
     */
    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
