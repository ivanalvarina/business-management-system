<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_code
 * @property string $company_name
 * @property string|null $trade_name
 * @property string|null $tin
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $logo
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_code', 'company_name', 'trade_name', 'tin', 'email', 'phone', 'address', 'logo', 'status'])]
class Company extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'company_client')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Vendor, $this>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'company_vendor')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
