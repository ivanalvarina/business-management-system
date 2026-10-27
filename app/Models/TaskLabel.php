<?php

namespace App\Models;

use Database\Factories\TaskLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['work_board_id', 'name', 'color'])]
class TaskLabel extends Model
{
    /** @use HasFactory<TaskLabelFactory> */
    use HasFactory;

    /** @return BelongsTo<WorkBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(WorkBoard::class, 'work_board_id');
    }

    /** @return BelongsToMany<WorkTask, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(WorkTask::class);
    }
}
