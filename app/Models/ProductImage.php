<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_service_id
 * @property string $image_path
 * @property string|null $original_filename
 * @property string $mime_type
 * @property int $file_size
 * @property int $sort_order
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_service_id', 'image_path', 'original_filename', 'mime_type', 'file_size', 'sort_order', 'is_primary'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

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
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
