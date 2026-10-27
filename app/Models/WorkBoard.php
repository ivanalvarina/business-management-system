<?php

namespace App\Models;

use Database\Factories\WorkBoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'color', 'status', 'created_by'])]
class WorkBoard extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /** @use HasFactory<WorkBoardFactory> */
    use HasFactory;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_board_user')->withTimestamps();
    }

    /** @return HasMany<WorkBoardColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(WorkBoardColumn::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<WorkTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkTask::class);
    }

    /** @return HasMany<WorkTask, $this> */
    public function workTasks(): HasMany
    {
        return $this->tasks();
    }

    /** @return HasMany<TaskLabel, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(TaskLabel::class)->orderBy('name');
    }
}
