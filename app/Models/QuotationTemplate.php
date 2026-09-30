<?php

namespace App\Models;

use Database\Factories\QuotationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $original_filename
 * @property string $stored_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $file_type
 * @property array<string, mixed>|null $config
 * @property bool $is_active
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $file_url
 */
#[Fillable(['company_id', 'name', 'original_filename', 'stored_path', 'mime_type', 'file_size', 'file_type', 'config', 'is_active', 'uploaded_by'])]
class QuotationTemplate extends Model
{
    /** @use HasFactory<QuotationTemplateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_filename' => $this->original_filename,
            'stored_path' => $this->stored_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'config' => $this->config,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'file_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->stored_path));
    }
}
