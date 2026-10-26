<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ForcedPasswordUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->must_change_password;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                Password::min(8),
                'confirmed',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Hash::check((string) $value, $this->user()->password)) {
                        $fail(__('The new password must be different from your temporary password.'));
                    }
                },
            ],
        ];
    }
}
