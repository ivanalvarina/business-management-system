<?php

namespace App\Models;

use Database\Factories\ProductServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property string $unit
 * @property string $default_price
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'type', 'name', 'description', 'unit', 'default_price', 'status'])]
class ProductService extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<ProductServiceFactory> */
    use HasFactory;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
        ];
    }
}
