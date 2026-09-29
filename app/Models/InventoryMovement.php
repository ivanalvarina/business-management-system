<?php

namespace App\Models;

use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $product_id
 * @property string $type
 * @property string $quantity
 * @property int $quantity_before
 * @property int $quantity_after
 * @property string $reference_type
 * @property int $reference_id
 * @property int|null $performed_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'product_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'reference_type', 'reference_id', 'performed_by', 'notes'])]
class InventoryMovement extends Model
{
    public const TYPE_IN = 'IN';

    public const TYPE_OUT = 'OUT';

    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<ProductService, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }
}
