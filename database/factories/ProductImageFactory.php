<?php

namespace Database\Factories;

use App\Models\ProductImage;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_service_id' => ProductService::factory([
                'type' => ProductService::TYPE_PRODUCT,
            ]),
            'image_path' => 'product-images/example.jpg',
            'original_filename' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }
}
