<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $module
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'module', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Collection<int, array{id: int, module: string, action: string, created_at: string|null, user: array{id: int, name: string}|null}>
     */
    public static function recentFor(Model $subject, int $limit = 8): Collection
    {
        return self::query()
            ->select(['id', 'user_id', 'module', 'action', 'created_at'])
            ->with('user:id,name')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $auditLog): array => [
                'id' => $auditLog->id,
                'module' => $auditLog->module,
                'action' => $auditLog->action,
                'created_at' => $auditLog->created_at?->toDateTimeString(),
                'user' => $auditLog->user ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->name,
                ] : null,
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
