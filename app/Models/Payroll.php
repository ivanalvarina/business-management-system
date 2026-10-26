<?php

namespace App\Models;

use Database\Factories\PayrollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $payroll_group
 * @property string $cutoff_type
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $pay_date
 * @property string $status
 * @property int $created_by
 * @property int|null $finalized_by
 * @property Carbon|null $finalized_at
 */
#[Fillable(['company_id', 'payroll_group', 'cutoff_type', 'period_start', 'period_end', 'pay_date', 'status', 'created_by', 'finalized_by', 'finalized_at'])]
class Payroll extends Model
{
    public const CUTOFF_FIRST_HALF = 'first_half';

    public const CUTOFF_SECOND_HALF = 'second_half';

    public const GROUP_CONTRACTUAL = 'contractual';

    public const GROUP_MIXED = 'mixed';

    public const GROUP_REGULAR = 'regular';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    /** @use HasFactory<PayrollFactory> */
    use HasFactory;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('employee_name');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** @return array<int, string> */
    public static function creatableGroups(): array
    {
        return [self::GROUP_REGULAR, self::GROUP_CONTRACTUAL];
    }

    /** @return array<int, string> */
    public static function cutoffTypes(): array
    {
        return [self::CUTOFF_FIRST_HALF, self::CUTOFF_SECOND_HALF];
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'finalized_at' => 'datetime'];
    }
}
