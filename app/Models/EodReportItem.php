<?php

namespace App\Models;

use Database\Factories\EodReportItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['eod_report_id', 'work_task_id', 'accomplishment', 'minutes_spent'])]
class EodReportItem extends Model
{
    /** @use HasFactory<EodReportItemFactory> */
    use HasFactory;

    /** @return BelongsTo<EodReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(EodReport::class, 'eod_report_id');
    }

    /** @return BelongsTo<WorkTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class, 'work_task_id');
    }

    /** @return HasMany<EodAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EodAttachment::class);
    }
}
