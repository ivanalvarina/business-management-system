<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->user()->can('users.view') || abort(403);

        $search = $request->string('search')->trim()->toString();

        return Inertia::render('admin/users/Index', [
            'filters' => [
                'search' => $search,
            ],
            'users' => User::query()
                ->select(['id', 'name', 'email', 'is_active', 'email_verified_at', 'created_at'])
                ->with('roles:id,name')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString()
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'created_at' => $user->created_at?->toDateString(),
                    'roles' => $user->roles->pluck('name')->values(),
                ]),
            'can' => [
                'create' => $request->user()->can('users.create'),
                'edit' => $request->user()->can('users.edit'),
                'delete' => $request->user()->can('users.delete'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $request->user()->can('users.create') || abort(403);

        return Inertia::render('admin/users/Create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
            'is_active' => $validated['is_active'],
        ]);

        $this->syncRoles($user, $validated['roles'] ?? []);
        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created and password reset link sent.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): Response
    {
        $request->user()->can('users.view') || abort(403);

        $user->load('roles:id,name');

        return Inertia::render('admin/users/Show', [
            'managedUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at?->toDateString(),
                'roles' => $user->roles->pluck('name')->values(),
            ],
            'can' => [
                'edit' => $request->user()->can('users.edit'),
                'delete' => $request->user()->can('users.delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user): Response
    {
        $request->user()->can('users.edit') || abort(403);

        $user->load('roles:id');

        return Inertia::render('admin/users/Edit', [
            'managedUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('id')->values(),
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        $this->syncRoles($user, $validated['roles'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->can('users.delete') || abort(403);

        if ($request->user()->is($user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete your own account.')]);

            return back();
        }

        $user->syncRoles([]);
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $request->user()->can('users.edit') || abort(403);

        $user->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User activated.')]);

        return back();
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $request->user()->can('users.edit') || abort(403);

        if ($request->user()->is($user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot deactivate your own account.')]);

            return back();
        }

        $user->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated.')]);

        return back();
    }

    public function sendPasswordResetLink(Request $request, User $user): RedirectResponse
    {
        $request->user()->can('users.edit') || abort(403);

        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password reset link sent.')]);

        return back();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->select(['id', 'name'])
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
            ])
            ->all();
    }

    /**
     * @param  array<int>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        $user->syncRoles(Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $roleIds)
            ->get());
    }
}
