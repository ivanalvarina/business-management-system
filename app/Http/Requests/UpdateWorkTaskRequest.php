<?php

namespace App\Http\Requests;

use App\Models\WorkTask;

class UpdateWorkTaskRequest extends StoreWorkTaskRequest
{
    public function authorize(): bool
    {
        $task = $this->route('work_task');

        return $task instanceof WorkTask && ($this->user()?->can('update', $task) ?? false);
    }
}
