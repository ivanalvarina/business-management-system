<?php

namespace App\Models;

use Database\Factories\ReceivingReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rr_no
 * @property int $company_id
 * @property int $purchase_order_id
 * @property int $vendor_id
 * @property string|null $invoice_no
 * @property Carbon $received_date
 * @property string|null $received_by_name
 * @property string|null $checked_by_name
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['rr_no', 'company_id', 'purchase_order_id', 'vendor_id', 'invoice_no', 'received_date', 'received_by_name', 'checked_by_name', 'status', 'notes', 'created_by'])]
class ReceivingReceipt extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<ReceivingReceiptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * @return HasMany<ReceivingReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceivingReceiptItem::class)->orderBy('sort_order');
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }
}
