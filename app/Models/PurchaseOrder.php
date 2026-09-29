<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $po_no
 * @property int $company_id
 * @property int $vendor_id
 * @property Carbon $po_date
 * @property Carbon|null $expected_delivery
 * @property string $currency
 * @property string $status
 * @property string|null $notes
 * @property string|null $terms_conditions
 * @property string $subtotal
 * @property string $discount
 * @property string $tax_amount
 * @property string $total_amount
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['po_no', 'company_id', 'vendor_id', 'po_date', 'expected_delivery', 'currency', 'subtotal', 'discount', 'tax_amount', 'total_amount', 'status', 'notes', 'terms_conditions', 'created_by'])]
class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FOR_APPROVAL = 'for_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT = 'sent';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<PurchaseOrderFactory> */
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
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<PurchaseRequest, $this>
     */
    public function purchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequest::class)->withTimestamps();
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
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
            self::STATUS_APPROVED => [self::STATUS_SENT, self::STATUS_CANCELLED],
            self::STATUS_SENT => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
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
            self::STATUS_SENT,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'expected_delivery' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
