<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $module,
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $request?->user()?->id,
            'module' => $module,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues === null ? null : $this->redact($oldValues),
            'new_values' => $newValues === null ? null : $this->redact($newValues),
            'ip_address' => $request?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordChanges(
        string $module,
        string $action,
        Model $subject,
        array $oldValues,
        array $newValues,
        ?Request $request = null,
    ): AuditLog {
        $newValues = Arr::except($newValues, ['updated_at']);
        $changedKeys = array_keys($newValues);

        return $this->record(
            module: $module,
            action: $action,
            subject: $subject,
            oldValues: Arr::only($oldValues, $changedKeys),
            newValues: $newValues,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redact(array $values): array
    {
        return collect($values)
            ->mapWithKeys(function (mixed $value, string $key): array {
                if ($this->isSensitiveKey($key)) {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->redact($value)];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = str($key)->lower()->toString();

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'recovery')
            || str_contains($normalized, 'remember');
    }
}
