<?php

namespace App\Http\Requests;

use App\Models\ProductService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductService::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:product_services,code'],
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
                if ($this->input('type') !== ProductService::TYPE_PRODUCT && count($this->file('images', [])) > 0) {
                    $validator->errors()->add('images', __('Services cannot have product images.'));
                }
            },
        ];
    }
}
