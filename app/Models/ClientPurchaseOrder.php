<?php

namespace App\Models;

use Database\Factories\ClientPurchaseOrderFactory;
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
 * @property int $company_id
 * @property int $client_id
 * @property int|null $quotation_id
 * @property string $client_po_no
 * @property Carbon $po_date
 * @property string $currency
 * @property string $amount
 * @property string $status
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'client_id', 'quotation_id', 'client_po_no', 'po_date', 'currency', 'amount', 'status', 'received_by', 'received_at', 'fulfilled_at', 'cancelled_at', 'notes'])]
class ClientPurchaseOrder extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<ClientPurchaseOrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsToMany<Quotation, $this>
     */
    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(Quotation::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
    }

    /**
     * @return HasMany<ClientPurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ClientPurchaseOrderItem::class)->orderBy('sort_order');
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::transitions()[$this->status] ?? [], true);
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null || in_array($this->status, [self::STATUS_FULFILLED, self::STATUS_COMPLETED], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::STATUS_RECEIVED => [self::STATUS_IN_REVIEW, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_IN_REVIEW => [self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_FULFILLED, self::STATUS_CANCELLED],
            self::STATUS_FULFILLED => [self::STATUS_COMPLETED],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_RECEIVED,
            self::STATUS_IN_REVIEW,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_FULFILLED,
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
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
