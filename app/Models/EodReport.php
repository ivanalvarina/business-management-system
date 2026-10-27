<?php

namespace App\Models;

use Database\Factories\EodReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'user_id', 'work_date', 'summary', 'blockers', 'next_plan', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at'])]
class EodReport extends Model
{
    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_SUBMITTED = 'submitted';

    /** @use HasFactory<EodReportFactory> */
    use HasFactory;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EodReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EodReportItem::class);
    }

    protected function casts(): array
    {
        return ['work_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
