<?php

namespace App\Http\Requests;

use App\Models\EodAttachment;
use App\Models\WorkTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEodReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $task = $this->route('work_task');

        return $task instanceof WorkTask && ($this->user()?->can('submitEod', $task) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'accomplishment' => ['required', 'string', 'max:10000'],
            'minutes_spent' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'next_plan' => ['nullable', 'string', 'max:5000'],
            'screenshots' => ['array', 'max:3'],
            'screenshots.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var WorkTask $task */
            $task = $this->route('work_task');
            $existingCount = EodAttachment::query()
                ->whereHas('item', fn ($query) => $query
                    ->where('work_task_id', $task->id)
                    ->whereHas('report', fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->whereDate('work_date', $this->date('work_date'))))
                ->count();

            if ($existingCount + count($this->file('screenshots', [])) > 3) {
                $validator->errors()->add('screenshots', __('A task may have up to three screenshots per EOD date.'));
            }
        }];
    }
}
