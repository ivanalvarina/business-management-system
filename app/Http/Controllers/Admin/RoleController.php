<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->user()->can('roles.view') || abort(403);

        $search = $request->string('search')->trim()->toString();

        return Inertia::render('admin/roles/Index', [
            'filters' => [
                'search' => $search,
            ],
            'roles' => Role::query()
                ->select(['id', 'name', 'created_at'])
                ->withCount(['permissions', 'users'])
                ->where('guard_name', 'web')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions_count,
                    'users_count' => $role->users_count,
                    'created_at' => $role->created_at?->toDateString(),
                ]),
            'can' => [
                'create' => $request->user()->can('roles.create'),
                'edit' => $request->user()->can('roles.edit'),
                'delete' => $request->user()->can('roles.delete'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $request->user()->can('roles.create') || abort(403);

        return Inertia::render('admin/roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $role = new Role;
        $role->name = $validated['name'];
        $role->guard_name = 'web';
        $role->save();

        $this->syncPermissions($role, $validated['permissions'] ?? []);
        $audit->record('roles', 'created', $role, newValues: [
            'name' => $role->name,
            'permission_ids' => $validated['permissions'] ?? [],
        ], request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.show', $role);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Role $role): Response
    {
        $request->user()->can('roles.view') || abort(403);

        $role->load('permissions:id,name')->loadCount('users');

        return Inertia::render('admin/roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions
                    ->sortBy('name')
                    ->pluck('name')
                    ->values(),
            ],
            'activityLogs' => AuditLog::recentFor($role),
            'can' => [
                'edit' => $request->user()->can('roles.edit'),
                'delete' => $request->user()->can('roles.delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Role $role): Response
    {
        $request->user()->can('roles.edit') || abort(403);

        $role->load('permissions:id');

        return Inertia::render('admin/roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('id')->values(),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $oldValues = [
            'name' => $role->name,
            'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
        ];

        $role->update([
            'name' => $validated['name'],
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);
        $audit->record('roles', 'updated', $role, oldValues: $oldValues, newValues: [
            'name' => $role->name,
            'permission_ids' => $validated['permissions'] ?? [],
        ], request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.show', $role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $request->user()->can('roles.delete') || abort(403);

        $role->loadCount('users');

        if ($role->users_count > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Roles assigned to users cannot be deleted.')]);

            return back();
        }

        $oldValues = ['name' => $role->name];
        $role->delete();
        $audit->record('roles', 'deleted', $role, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array<int, array{name: string, permissions: array<int, array{id: int, name: string}>}>
     */
    private function permissionGroups(): array
    {
        return Permission::query()
            ->select(['id', 'name'])
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->headline()->toString())
            ->map(fn ($permissions, string $group): array => [
                'name' => $group,
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'id' => (int) $permission->id,
                    'name' => $permission->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $permissionIds
     */
    private function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->syncPermissions(Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $permissionIds)
            ->get());
    }
}
