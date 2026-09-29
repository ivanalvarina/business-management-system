<?php

namespace App\Models;

use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $pr_no
 * @property int $company_id
 * @property int|null $vendor_id
 * @property Carbon $request_date
 * @property Carbon|null $date_required
 * @property string|null $client_project
 * @property string|null $requested_by_name
 * @property string|null $checked_by_name
 * @property string|null $noted_by_name
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['pr_no', 'company_id', 'vendor_id', 'request_date', 'date_required', 'client_project', 'requested_by_name', 'checked_by_name', 'noted_by_name', 'currency', 'subtotal', 'tax_amount', 'total_amount', 'status', 'notes', 'created_by'])]
class PurchaseRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FOR_APPROVAL = 'for_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::transitions()[$this->status] ?? [], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_FOR_APPROVAL, self::STATUS_CANCELLED],
            self::STATUS_FOR_APPROVAL => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_REJECTED => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_FOR_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'date_required' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
