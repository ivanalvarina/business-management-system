<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $employee_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $birth_date
 * @property Carbon $hire_date
 * @property string|null $department
 * @property string $job_title
 * @property string $employment_type
 * @property string $pay_frequency
 * @property string $basic_salary
 * @property string|null $tin
 * @property string|null $sss_number
 * @property string|null $philhealth_number
 * @property string|null $pagibig_number
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string $status
 */
#[Fillable(['company_id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'address', 'birth_date', 'hire_date', 'department', 'job_title', 'employment_type', 'pay_frequency', 'basic_salary', 'tin', 'sss_number', 'philhealth_number', 'pagibig_number', 'bank_name', 'bank_account_name', 'bank_account_number', 'status'])]
class Employee extends Model
{
    public const EMPLOYMENT_CONTRACTUAL = 'contractual';

    public const EMPLOYMENT_PROBATIONARY = 'probationary';

    public const EMPLOYMENT_REGULAR = 'regular';

    public const PAY_FREQUENCY_MONTHLY = 'monthly';

    public const PAY_FREQUENCY_SEMI_MONTHLY = 'semi-monthly';

    public const PAY_FREQUENCY_WEEKLY = 'weekly';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    /**
     * @return array<int, string>
     */
    public static function employmentTypes(): array
    {
        return [
            self::EMPLOYMENT_REGULAR,
            self::EMPLOYMENT_PROBATIONARY,
            self::EMPLOYMENT_CONTRACTUAL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function payFrequencies(): array
    {
        return [
            self::PAY_FREQUENCY_SEMI_MONTHLY,
            self::PAY_FREQUENCY_MONTHLY,
            self::PAY_FREQUENCY_WEEKLY,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }
}
