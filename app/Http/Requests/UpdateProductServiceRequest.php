<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $productService = $this->route('product_service');

        return $productService instanceof ProductService
            && ($this->user()?->can('update', $productService) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ProductService $productService */
        $productService = $this->route('product_service');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('product_services', 'code')->ignore($productService)],
            'type' => ['required', Rule::in([ProductService::TYPE_PRODUCT, ProductService::TYPE_SERVICE])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:50'],
            'default_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['required', Rule::in([ProductService::STATUS_ACTIVE, ProductService::STATUS_INACTIVE])],
            'is_public' => ['boolean'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'existing_image_ids' => ['array'],
            'existing_image_ids.*' => ['integer', Rule::exists('product_images', 'id')],
            'primary_existing_image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')],
            'primary_new_image_index' => ['nullable', 'integer', 'min:0', 'max:4'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var ProductService $productService */
                $productService = $this->route('product_service');
                $rawExistingImageIds = $this->input('existing_image_ids', []);
                $existingImageIds = array_values(array_unique(array_map(
                    fn (mixed $imageId): int => (int) $imageId,
                    is_array($rawExistingImageIds) ? $rawExistingImageIds : [],
                )));
                $newImageCount = count($this->file('images', []));

                $ownedExistingCount = $productService->images()
                    ->whereIn('id', $existingImageIds)
                    ->count();

                if ($ownedExistingCount !== count($existingImageIds)) {
                    $validator->errors()->add('existing_image_ids', __('One or more selected images do not belong to this product.'));
                }

                if ($this->filled('primary_existing_image_id') && ! in_array($this->integer('primary_existing_image_id'), $existingImageIds, true)) {
                    $validator->errors()->add('primary_existing_image_id', __('The primary image must be one of the kept images.'));
                }

                if ($this->input('type') !== ProductService::TYPE_PRODUCT) {
                    if ($newImageCount > 0 || $existingImageIds !== []) {
                        $validator->errors()->add('images', __('Services cannot have product images.'));
                    }

                    return;
                }

                if (count($existingImageIds) + $newImageCount > 5) {
                    $validator->errors()->add('images', __('A product may have a maximum of 5 images.'));
                }
            },
        ];
    }
}
