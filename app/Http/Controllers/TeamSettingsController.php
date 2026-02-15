<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->team;
        $members = $team->users()->get(['id', 'name', 'email', 'created_at']);
        $tokens = $request->user()->tokens()->latest()->get(['id', 'name', 'created_at', 'last_used_at']);

        return Inertia::render('Settings/Index', compact('team', 'members', 'tokens'));
    }

    public function updateTeam(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $request->user()->team->update($validated);

        return back()->with('success', 'Team updated.');
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $token = $request->user()->createToken($validated['name']);

        return back()->with('success', 'API Token: '.$token->plainTextToken);
    }

    public function deleteToken(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'Token revoked.');
    }
}
