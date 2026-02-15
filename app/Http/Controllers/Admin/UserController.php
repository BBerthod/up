<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('team')->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/Users/Create', [
            'assignableRoles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $role = UserRole::from($validated['role'] ?? UserRole::MEMBER->value);

        DB::transaction(function () use ($validated, $role) {
            $team = Team::create([
                'name' => $validated['name']."'s Team",
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'team_id' => $team->id,
            ]);

            $user->role = $role;
            $user->save();
        });

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(Request $request, User $user)
    {
        $actor = $request->user();
        $canChangeRole = $actor->role->level() > $user->role->level() || $actor->id === $user->id && $actor->isSuperAdmin();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('team'),
            'assignableRoles' => $this->assignableRoles($actor),
            'canChangeRole' => $canChangeRole,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if (isset($validated['role'])) {
            $actor = $request->user();
            $newRole = UserRole::from($validated['role']);

            if (in_array($newRole, $actor->role->assignableRoles())) {
                $user->role = $newRole;
                $user->save();
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }

        if ($user->role->level() >= $request->user()->role->level()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete a user with equal or higher role.');
        }

        $teamId = $user->team_id;

        $user->delete();

        if ($teamId && User::where('team_id', $teamId)->doesntExist()) {
            Team::where('id', $teamId)->delete();
        }

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    private function assignableRoles(User $actor): array
    {
        return array_map(fn (UserRole $r) => [
            'value' => $r->value,
            'label' => $r->label(),
        ], $actor->role->assignableRoles());
    }
}
