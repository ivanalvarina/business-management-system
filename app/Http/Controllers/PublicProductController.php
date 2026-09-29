<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProductImage;
use App\Models\ProductService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicProductController extends Controller
{
    public function show(string $publicToken): Response
    {
        $product = ProductService::query()
            ->select(['id', 'code', 'type', 'name', 'description', 'unit', 'default_price', 'quantity', 'status', 'is_public', 'public_token'])
            ->with(['images', 'primaryImage'])
            ->where('public_token', $publicToken)
            ->where('type', ProductService::TYPE_PRODUCT)
            ->where('status', ProductService::STATUS_ACTIVE)
            ->where('is_public', true)
            ->firstOrFail();

        return Inertia::render('public/Product', [
            'product' => [
                'code' => $product->code,
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit,
                'default_price' => $product->default_price,
                'availability' => $this->availability($product),
                'company' => $this->companyPayload(),
                'primary_image' => $product->primaryImage ? $this->imagePayload($product->primaryImage) : null,
                'images' => $product->images->map(fn (ProductImage $image): array => $this->imagePayload($image))->values(),
            ],
        ]);
    }

    private function availability(ProductService $product): string
    {
        if ($product->quantity <= 0) {
            return 'out_of_stock';
        }

        if ($product->quantity <= 5) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * @return array<string, mixed>
     */
    private function imagePayload(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => Storage::disk('public')->url($image->image_path),
            'is_primary' => $image->is_primary,
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function companyPayload(): ?array
    {
        $company = Company::query()
            ->select(['company_name', 'trade_name', 'email', 'phone', 'logo'])
            ->active()
            ->orderBy('company_name')
            ->orderBy('id')
            ->first();

        if ($company === null) {
            return null;
        }

        return [
            'name' => $company->trade_name ?: $company->company_name,
            'logo_url' => $company->logo === null ? null : Storage::disk('public')->url($company->logo),
            'phone' => $company->phone,
            'email' => $company->email,
        ];
    }
}
