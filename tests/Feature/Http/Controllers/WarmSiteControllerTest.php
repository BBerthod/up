<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\RunWarmSite;
use App\Models\Team;
use App\Models\User;
use App\Models\WarmRun;
use App\Models\WarmSite;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WarmSiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function createAuthenticatedUser(): User
    {
        $team = Team::factory()->create();
        $user = User::factory()->create([
            'team_id' => $team->id,
            'role' => 'member',
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_warming_index(): void
    {
        $response = $this->get(route('warming.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_warming_index(): void
    {
        $user = $this->createAuthenticatedUser();
        WarmSite::factory()->count(2)->for($user->team)->create();

        $response = $this->get(route('warming.index'));

        $response->assertStatus(200);
    }

    public function test_user_only_sees_own_team_sites(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();

        WarmSite::factory()->for($user->team)->create(['name' => 'My Site']);
        WarmSite::factory()->for($otherTeam)->create(['name' => 'Other Team Site']);

        $response = $this->get(route('warming.index'));

        $response->assertStatus(200);
        $this->assertSame(1, WarmSite::count());
    }

    public function test_user_can_create_warm_site_with_urls_mode(): void
    {
        $user = $this->createAuthenticatedUser();

        $data = [
            'name' => 'My Site',
            'domain' => 'example.com',
            'mode' => 'urls',
            'urls' => ['https://example.com/page1', 'https://example.com/page2'],
            'frequency_minutes' => 60,
            'max_urls' => 50,
        ];

        $response = $this->post(route('warming.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('warm_sites', [
            'name' => 'My Site',
            'domain' => 'example.com',
            'mode' => 'urls',
            'team_id' => $user->team_id,
        ]);
    }

    public function test_user_can_create_warm_site_with_sitemap_mode(): void
    {
        $user = $this->createAuthenticatedUser();

        $data = [
            'name' => 'Sitemap Site',
            'domain' => 'sitemap-example.com',
            'mode' => 'sitemap',
            'sitemap_url' => 'https://sitemap-example.com/sitemap.xml',
            'frequency_minutes' => 120,
            'max_urls' => 100,
        ];

        $response = $this->post(route('warming.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('warm_sites', [
            'name' => 'Sitemap Site',
            'domain' => 'sitemap-example.com',
            'mode' => 'sitemap',
            'team_id' => $user->team_id,
        ]);
    }

    public function test_validation_rejects_invalid_domain(): void
    {
        $user = $this->createAuthenticatedUser();

        $data = [
            'name' => 'Bad Domain',
            'domain' => 'not a domain!!!',
            'mode' => 'urls',
            'urls' => ['https://example.com/'],
            'frequency_minutes' => 60,
            'max_urls' => 50,
        ];

        $response = $this->post(route('warming.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseCount('warm_sites', 0);
    }

    public function test_validation_rejects_duplicate_domain_per_team(): void
    {
        $user = $this->createAuthenticatedUser();
        WarmSite::factory()->for($user->team)->create(['domain' => 'duplicate.com']);

        $data = [
            'name' => 'Duplicate',
            'domain' => 'duplicate.com',
            'mode' => 'urls',
            'urls' => ['https://duplicate.com/'],
            'frequency_minutes' => 60,
            'max_urls' => 50,
        ];

        $response = $this->post(route('warming.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseCount('warm_sites', 1);
    }

    public function test_user_can_view_warm_site_show(): void
    {
        $user = $this->createAuthenticatedUser();
        $site = WarmSite::factory()->for($user->team)->create();
        WarmRun::factory()->count(2)->for($site, 'warmSite')->create();

        $response = $this->get(route('warming.show', $site));

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_other_teams_site(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();
        $otherSite = WarmSite::factory()->for($otherTeam)->create();

        $response = $this->get(route('warming.show', $otherSite));

        $response->assertNotFound();
    }

    public function test_user_can_update_warm_site(): void
    {
        $user = $this->createAuthenticatedUser();
        $site = WarmSite::factory()->for($user->team)->create(['name' => 'Old Name']);

        $response = $this->put(route('warming.update', $site), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('warm_sites', [
            'id' => $site->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_cannot_update_other_teams_site(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();
        $otherSite = WarmSite::factory()->for($otherTeam)->create();

        $response = $this->put(route('warming.update', $otherSite), [
            'name' => 'Hacked Name',
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_warm_site(): void
    {
        $user = $this->createAuthenticatedUser();
        $site = WarmSite::factory()->for($user->team)->create();

        $response = $this->delete(route('warming.destroy', $site));

        $response->assertRedirect(route('warming.index'));
        $this->assertDatabaseMissing('warm_sites', ['id' => $site->id]);
    }

    public function test_user_cannot_delete_other_teams_site(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();
        $otherSite = WarmSite::factory()->for($otherTeam)->create();

        $response = $this->delete(route('warming.destroy', $otherSite));

        $response->assertNotFound();
        $this->assertDatabaseHas('warm_sites', ['id' => $otherSite->id]);
    }

    public function test_warm_now_dispatches_job(): void
    {
        Queue::fake();

        $user = $this->createAuthenticatedUser();
        $site = WarmSite::factory()->for($user->team)->create();

        $response = $this->post(route('warming.warm-now', $site));

        $response->assertRedirect();
        Queue::assertPushed(RunWarmSite::class, function ($job) use ($site) {
            return $job->warmSite->id === $site->id;
        });
    }

    public function test_user_cannot_warm_now_other_teams_site(): void
    {
        Queue::fake();

        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();
        $otherSite = WarmSite::factory()->for($otherTeam)->create();

        $response = $this->post(route('warming.warm-now', $otherSite));

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }
}
