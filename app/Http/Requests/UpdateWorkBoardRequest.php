<?php

namespace App\Http\Requests;

use App\Models\WorkBoard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkBoardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $board = $this->route('work_board');

        return $board instanceof WorkBoard && ($this->user()?->can('update', $board) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['required', 'string', Rule::in(['blue', 'emerald', 'amber', 'rose', 'violet', 'slate'])],
            'status' => ['required', 'string', Rule::in([WorkBoard::STATUS_ACTIVE, WorkBoard::STATUS_ARCHIVED])],
            'member_ids' => ['array', 'max:50'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('member_ids')) {
                return;
            }

            /** @var WorkBoard $board */
            $board = $this->route('work_board');
            $memberIds = $this->safe()->array('member_ids');

            if ($board->company->users()->whereKey($memberIds)->count() !== count($memberIds)) {
                $validator->errors()->add('member_ids', __('Every board member must belong to the board company.'));
            }
        }];
    }
}
