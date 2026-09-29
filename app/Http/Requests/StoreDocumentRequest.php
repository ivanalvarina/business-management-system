<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documentable_type' => ['required', 'string'],
            'documentable_id' => ['required', 'integer'],
            'document_type' => ['required', 'string', 'max:100'],
            'expiration_date' => ['nullable', 'date'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png',
            ],
        ];
    }
}
