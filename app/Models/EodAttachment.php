<?php

namespace App\Models;

use Database\Factories\EodAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['eod_report_item_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class EodAttachment extends Model
{
    /** @use HasFactory<EodAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<EodReportItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(EodReportItem::class, 'eod_report_item_id');
    }
}
