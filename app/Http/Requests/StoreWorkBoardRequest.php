<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\WorkBoard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkBoardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkBoard::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['required', 'string', Rule::in(['blue', 'emerald', 'amber', 'rose', 'violet', 'slate'])],
            'member_ids' => ['array', 'max:50'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['company_id', 'member_ids'])) {
                return;
            }

            $company = Company::find($this->integer('company_id'));

            if (! $company || ! $company->isActive() || ! $this->user()?->canAccessCompany($company)) {
                $validator->errors()->add('company_id', __('Select an active company you can access.'));

                return;
            }

            $memberIds = $this->safe()->array('member_ids');
            $validMemberCount = $company->users()->whereKey($memberIds)->count();

            if ($validMemberCount !== count($memberIds)) {
                $validator->errors()->add('member_ids', __('Every board member must belong to the selected company.'));
            }
        }];
    }
}
