<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('team')
            ->paginate(15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->is_admin ? 'admin' : 'member',
                'created_at' => $user->created_at,
                'team' => $user->team,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'assignableRoles' => $this->getAssignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|confirmed|min:8',
            'role' => 'sometimes|string|in:admin,member',
        ]);

        DB::transaction(function () use ($validated) {
            $team = Team::create([
                'name' => $validated['name']."'s Team",
            ]);

            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'team_id' => $team->id,
                'is_admin' => ($validated['role'] ?? 'member') === 'admin',
            ]);
        });

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->is_admin ? 'admin' : 'member',
                'team' => $user->team,
            ],
            'assignableRoles' => $this->getAssignableRoles(),
            'canChangeRole' => $request->user()->id !== $user->id,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|confirmed|min:8',
            'role' => 'sometimes|string|in:admin,member',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['role']) && $request->user()->id !== $user->id) {
            $updateData['is_admin'] = $validated['role'] === 'admin';
        }

        $user->update($updateData);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }

        $teamId = $user->team_id;

        $user->delete();

        if ($teamId && User::where('team_id', $teamId)->doesntExist()) {
            Team::where('id', $teamId)->delete();
        }

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    private function getAssignableRoles(): array
    {
        return [
            ['value' => 'member', 'label' => 'Member'],
            ['value' => 'admin', 'label' => 'Admin'],
        ];
    }
}
