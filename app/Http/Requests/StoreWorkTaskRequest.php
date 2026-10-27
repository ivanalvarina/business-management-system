<?php

namespace App\Http\Requests;

use App\Models\WorkBoard;
use App\Models\WorkTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $board = $this->route('work_board');

        return $board instanceof WorkBoard
            && $this->user()->can('create', WorkTask::class)
            && $this->user()->can('view', $board);
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
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', 'string', Rule::in(WorkTask::priorities())],
            'due_date' => ['nullable', 'date'],
            'label_ids' => ['array', 'max:20'],
            'label_ids.*' => ['integer', 'distinct', 'exists:task_labels,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var WorkBoard $board */
            $board = $this->route('work_board');

            if (! $board->columns()->whereKey($this->integer('work_board_column_id'))->exists()) {
                $validator->errors()->add('work_board_column_id', __('Select a column from this board.'));
            }

            $assignedTo = $this->integer('assigned_to');
            $existingTask = $this->route('work_task');
            $isChangingAssignee = ! $existingTask instanceof WorkTask || $existingTask->assigned_to !== $assignedTo;

            if ($assignedTo > 0 && ! $board->company->users()->where('is_active', true)->whereKey($assignedTo)->exists()) {
                $validator->errors()->add('assigned_to', __('The assignee must be an active user with access to this company.'));
            }

            if ($assignedTo > 0 && $isChangingAssignee && $assignedTo !== $this->user()->id && ! $this->user()->can('work-tasks.assign')) {
                $validator->errors()->add('assigned_to', __('You are not allowed to assign tasks to another user.'));
            }

            $labelIds = $this->array('label_ids');

            if ($board->labels()->whereKey($labelIds)->count() !== count($labelIds)) {
                $validator->errors()->add('label_ids', __('Every label must belong to this board.'));
            }
        }];
    }
}
