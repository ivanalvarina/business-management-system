<?php

namespace App\Http\Requests;

use App\Models\WorkBoard;
use App\Models\WorkTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveWorkTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $task = $this->route('work_task');

        return $task instanceof WorkTask && ($this->user()?->can('move', $task) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_board_column_id' => ['required', 'integer', 'exists:work_board_columns,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('work_board_column_id')) {
                return;
            }

            /** @var WorkBoard $board */
            $board = $this->route('work_board');

            if (! $board->columns()->whereKey($this->integer('work_board_column_id'))->exists()) {
                $validator->errors()->add('work_board_column_id', __('Select a column from this board.'));
            }
        }];
    }
}
