<?php

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $quotation_no
 * @property int $company_id
 * @property int $client_id
 * @property Carbon $quotation_date
 * @property Carbon|null $valid_until
 * @property string $currency
 * @property string $status
 * @property string|null $notes
 * @property string|null $terms_conditions
 * @property int|null $quotation_template_id
 * @property array<string, mixed>|null $quotation_template_snapshot
 * @property string $subtotal
 * @property string $discount
 * @property string $tax_amount
 * @property string $total_amount
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['quotation_no', 'company_id', 'client_id', 'quotation_date', 'valid_until', 'currency', 'status', 'notes', 'terms_conditions', 'quotation_template_id', 'quotation_template_snapshot', 'subtotal', 'discount', 'tax_amount', 'total_amount', 'created_by'])]
class Quotation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FOR_APPROVAL = 'for_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<QuotationFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<QuotationTemplate, $this>
     */
    public function quotationTemplate(): BelongsTo
    {
        return $this->belongsTo(QuotationTemplate::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
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
            self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_CANCELLED],
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
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'quotation_template_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
