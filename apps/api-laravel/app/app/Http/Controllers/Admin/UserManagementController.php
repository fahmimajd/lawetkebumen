<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        $roles = [
            Role::Admin->value,
            Role::Agent->value,
        ];

        return view('settings.users.index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('settings.users.index')
            ->with('status', 'User created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('settings.users.index')
            ->with('status', 'User updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return redirect()
            ->route('settings.users.index')
            ->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if ($currentUser && $currentUser->is($user)) {
            return redirect()
                ->route('settings.users.index')
                ->with('status', 'Cannot delete your own account.');
        }

        if ($user->isAdmin()) {
            $adminCount = User::query()
                ->where('role', Role::Admin->value)
                ->count();
            if ($adminCount <= 1) {
                return redirect()
                    ->route('settings.users.index')
                    ->with('status', 'Cannot delete the last admin.');
            }
        }

        $user->delete();

        return redirect()
            ->route('settings.users.index')
            ->with('status', 'User deleted.');
    }
}
