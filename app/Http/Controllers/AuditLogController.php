<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('audit-logs.view') || abort(403);

        $search = $request->string('search')->trim()->toString();
        $module = $request->string('module')->trim()->toString();
        $action = $request->string('action')->trim()->toString();
        $dateFrom = $request->date('date_from')?->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay();
        $userId = $request->integer('user_id');

        return Inertia::render('audit-logs/Index', [
            'filters' => [
                'search' => $search,
                'module' => $module === '' ? 'all' : $module,
                'action' => $action === '' ? 'all' : $action,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'user_id' => $userId > 0 ? $userId : null,
            ],
            'users' => $this->userOptions(),
            'modules' => AuditLog::query()
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'auditLogs' => AuditLog::query()
                ->with('user:id,name,email')
                ->when($userId > 0, fn (Builder $query) => $query->where('user_id', $userId))
                ->when($module !== '' && $module !== 'all', fn (Builder $query) => $query->where('module', $module))
                ->when($action !== '' && $action !== 'all', fn (Builder $query) => $query->where('action', $action))
                ->when($dateFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', $dateFrom))
                ->when($dateTo !== null, fn (Builder $query) => $query->where('created_at', '<=', $dateTo))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('module', 'like', "%{$search}%")
                            ->orWhere('action', 'like', "%{$search}%")
                            ->orWhere('subject_type', 'like', "%{$search}%")
                            ->orWhere('subject_id', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%"));
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (AuditLog $auditLog): array => $this->payload($auditLog)),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->id,
            'module' => $auditLog->module,
            'action' => $auditLog->action,
            'subject_type' => $auditLog->subject_type === null ? null : class_basename($auditLog->subject_type),
            'subject_id' => $auditLog->subject_id,
            'old_values' => $auditLog->old_values,
            'new_values' => $auditLog->new_values,
            'ip_address' => $auditLog->ip_address,
            'created_at' => $auditLog->created_at?->toDateTimeString(),
            'user' => $auditLog->user ? [
                'id' => $auditLog->user->id,
                'name' => $auditLog->user->name,
                'email' => $auditLog->user->email,
            ] : null,
        ];
    }
}
