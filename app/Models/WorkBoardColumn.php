<?php

namespace App\Models;

use Database\Factories\WorkBoardColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['work_board_id', 'name', 'color', 'position', 'is_done'])]
class WorkBoardColumn extends Model
{
    /** @use HasFactory<WorkBoardColumnFactory> */
    use HasFactory;

    /** @return BelongsTo<WorkBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(WorkBoard::class, 'work_board_id');
    }

    /** @return HasMany<WorkTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkTask::class)->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }
}
