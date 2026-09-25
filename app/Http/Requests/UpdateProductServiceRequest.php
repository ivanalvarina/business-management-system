<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'status' => ['required', Rule::in([ProductService::STATUS_ACTIVE, ProductService::STATUS_INACTIVE])],
        ];
    }
}
