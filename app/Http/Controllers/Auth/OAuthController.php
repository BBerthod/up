<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToProvider(string $provider): RedirectResponse
    {
        if (! in_array($provider, ['google', 'github'])) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(string $provider): RedirectResponse
    {
        if (! in_array($provider, ['google', 'github'])) {
            abort(404);
        }

        $socialiteUser = Socialite::driver($provider)->stateless()->user();

        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('error', 'No account found for this email. Please contact your administrator.');
        }

        if ($user->oauth_provider && $user->oauth_provider !== $provider) {
            return redirect()
                ->route('login')
                ->with('error', 'This account is linked to a different provider. Please use '.$user->oauth_provider.' to log in.');
        }

        if (! $user->oauth_provider) {
            $user->update([
                'oauth_provider' => $provider,
                'oauth_id' => $socialiteUser->getId(),
            ]);
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
