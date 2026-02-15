<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteCallback(string $provider, string $email, string $id): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getId')->andReturn($id);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_invalid_provider_returns_404(): void
    {
        $response = $this->get('/auth/invalid/redirect');

        $response->assertNotFound();
    }

    public function test_oauth_callback_with_unknown_email_redirects_to_login(): void
    {
        $this->mockSocialiteCallback('google', 'unknown@example.com', '99999');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_oauth_callback_with_existing_user_logs_in(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'team_id' => $team->id,
        ]);

        $this->mockSocialiteCallback('google', 'test@example.com', '12345');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertEquals('google', $user->oauth_provider);
        $this->assertEquals('12345', $user->oauth_id);
    }

    public function test_oauth_callback_rejects_different_provider(): void
    {
        $team = Team::factory()->create();
        User::factory()->create([
            'email' => 'test@example.com',
            'team_id' => $team->id,
            'oauth_provider' => 'google',
            'oauth_id' => '12345',
        ]);

        $this->mockSocialiteCallback('github', 'test@example.com', '67890');

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }
}
