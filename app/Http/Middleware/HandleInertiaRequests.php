<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => $this->getAuthData(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'message' => fn () => $request->session()->get('message'),
                'newToken' => fn () => $request->session()->get('newToken'),
                'link' => fn () => $request->session()->get('link'),
                'linkText' => fn () => $request->session()->get('linkText'),
            ],
        ]);
    }

    private function getAuthData(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return ['user' => null, 'team' => null];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_admin' => $user->isAdmin(),
            ],
            'team' => $user->team ? [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ] : null,
        ];
    }
}
