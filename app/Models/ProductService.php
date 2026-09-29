<?php

namespace App\Models;

use Database\Factories\ProductServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property string $unit
 * @property string $default_price
 * @property int $quantity
 * @property string $status
 * @property string|null $public_token
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'type', 'name', 'description', 'unit', 'default_price', 'quantity', 'status', 'public_token', 'is_public'])]
class ProductService extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<ProductServiceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductService $productService): void {
            $productService->public_token ??= self::generatePublicToken();

            if ($productService->type === self::TYPE_SERVICE) {
                $productService->quantity = 0;
            }
        });

        static::saving(function (ProductService $productService): void {
            if ($productService->type === self::TYPE_SERVICE) {
                $productService->quantity = 0;
            }
        });
    }

    /**
     * @param  Builder<ProductService>  $query
     * @return Builder<ProductService>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isProduct(): bool
    {
        return $this->type === self::TYPE_PRODUCT;
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id')->latest();
    }

    public static function generatePublicToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (self::query()->where('public_token', $token)->exists());

        return $token;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'quantity' => 'integer',
            'is_public' => 'boolean',
        ];
    }
}
