<?php

namespace App\Models;

use Database\Factories\WorkTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['work_board_id', 'work_board_column_id', 'assigned_to', 'title', 'description', 'priority', 'due_date', 'position', 'completed_at', 'created_by'])]
class WorkTask extends Model
{
    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_URGENT = 'urgent';

    /** @use HasFactory<WorkTaskFactory> */
    use HasFactory;

    /** @return array<int, string> */
    public static function priorities(): array
    {
        return [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    /** @return BelongsTo<WorkBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(WorkBoard::class, 'work_board_id');
    }

    /** @return BelongsTo<WorkBoardColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(WorkBoardColumn::class, 'work_board_column_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<TaskLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class);
    }

    /** @return HasMany<EodReportItem, $this> */
    public function eodItems(): HasMany
    {
        return $this->hasMany(EodReportItem::class);
    }

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }
}
