<?php

namespace App\Models;

use Database\Factories\PayrollSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'hours_per_day', 'payroll_days_basis', 'monthly_workdays_divisor', 'annual_workdays_divisor', 'regular_ot_multiplier', 'special_holiday_multiplier', 'special_holiday_ot_multiplier', 'regular_holiday_multiplier', 'regular_holiday_ot_multiplier'])]
class PayrollSetting extends Model
{
    /** @use HasFactory<PayrollSettingFactory> */
    use HasFactory;

    public static function defaults(): array
    {
        return [
            'hours_per_day' => 8,
            'payroll_days_basis' => 13,
            'monthly_workdays_divisor' => 26,
            'annual_workdays_divisor' => 261,
            'regular_ot_multiplier' => 1.25,
            'special_holiday_multiplier' => 1.30,
            'special_holiday_ot_multiplier' => 1.69,
            'regular_holiday_multiplier' => 2.00,
            'regular_holiday_ot_multiplier' => 2.60,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return collect(array_keys(self::defaults()))
            ->mapWithKeys(fn (string $attribute): array => [$attribute => 'decimal:2'])
            ->all();
    }
}
