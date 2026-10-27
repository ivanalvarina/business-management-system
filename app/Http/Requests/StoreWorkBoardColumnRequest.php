<?php

namespace App\Http\Requests;

use App\Models\WorkBoard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkBoardColumnRequest extends FormRequest
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
        $board = $this->route('work_board');
        $boardId = $board instanceof WorkBoard ? $board->id : 0;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('work_board_columns')->where('work_board_id', $boardId),
            ],
            'color' => ['required', 'string', Rule::in(['blue', 'emerald', 'amber', 'rose', 'violet', 'slate'])],
            'is_done' => ['required', 'boolean'],
        ];
    }
}
