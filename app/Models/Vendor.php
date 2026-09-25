<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $vendor_code
 * @property string $vendor_name
 * @property string|null $trade_name
 * @property string|null $tin
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property int $vendor_category_id
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vendor_code', 'vendor_name', 'trade_name', 'tin', 'email', 'phone', 'address', 'vendor_category_id', 'status', 'created_by'])]
class Vendor extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<VendorCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class, 'vendor_category_id');
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_vendor')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * @return MorphMany<ContactPerson, $this>
     */
    public function contactPeople(): MorphMany
    {
        return $this->morphMany(ContactPerson::class, 'contactable')
            ->orderByDesc('is_primary')
            ->orderBy('name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Vendor>  $query
     * @return Builder<Vendor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->hasGlobalCompanyAccess()) {
            return true;
        }

        return $this->companies()
            ->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))
            ->exists();
    }
}
