<?php

namespace App\Models;

use Database\Factories\ContactPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $contactable_type
 * @property int $contactable_id
 * @property string $name
 * @property string|null $position
 * @property string|null $department
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'department', 'email', 'phone', 'mobile', 'is_primary'])]
class ContactPerson extends Model
{
    /** @use HasFactory<ContactPersonFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
