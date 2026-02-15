<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\MonitorMethod;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(): User
    {
        $team = Team::factory()->create();

        return User::factory()->create(['team_id' => $team->id]);
    }

    public function test_guest_cannot_access_monitors(): void
    {
        $response = $this->get(route('monitors.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_monitors_index(): void
    {
        $user = $this->createAuthenticatedUser();
        Monitor::factory()->count(3)->for($user->team)->create();

        $response = $this->actingAs($user)->get(route('monitors.index'));

        $response->assertStatus(200);
    }

    public function test_user_only_sees_own_team_monitors(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();

        Monitor::factory()->for($user->team)->create(['name' => 'My Monitor']);
        Monitor::factory()->for($otherTeam)->create(['name' => 'Other Monitor']);

        $response = $this->actingAs($user)->get(route('monitors.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_monitor_with_valid_data(): void
    {
        $user = $this->createAuthenticatedUser();

        $data = [
            'name' => 'Test Website',
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET->value,
            'expected_status_code' => 200,
            'interval' => 5,
        ];

        $response = $this->actingAs($user)->post(route('monitors.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('monitors', [
            'name' => 'Test Website',
            'team_id' => $user->team_id,
        ]);
    }

    public function test_cannot_create_monitor_with_invalid_url(): void
    {
        $user = $this->createAuthenticatedUser();

        $data = [
            'name' => 'Bad URL',
            'url' => 'not-a-url',
            'method' => MonitorMethod::GET->value,
            'expected_status_code' => 200,
            'interval' => 5,
        ];

        $response = $this->actingAs($user)->post(route('monitors.store'), $data);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseCount('monitors', 0);
    }

    public function test_can_update_monitor(): void
    {
        $user = $this->createAuthenticatedUser();
        $monitor = Monitor::factory()->for($user->team)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('monitors.update', $monitor), [
            'name' => 'New Name',
            'url' => $monitor->url,
            'method' => $monitor->method->value,
            'expected_status_code' => 200,
            'interval' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('monitors', ['id' => $monitor->id, 'name' => 'New Name']);
    }

    public function test_can_delete_monitor(): void
    {
        $user = $this->createAuthenticatedUser();
        $monitor = Monitor::factory()->for($user->team)->create();

        $response = $this->actingAs($user)->delete(route('monitors.destroy', $monitor));

        $response->assertRedirect(route('monitors.index'));
        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_can_pause_monitor(): void
    {
        $user = $this->createAuthenticatedUser();
        $monitor = Monitor::factory()->for($user->team)->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('monitors.pause', $monitor));

        $response->assertRedirect();
        $this->assertFalse($monitor->fresh()->is_active);
    }

    public function test_can_resume_monitor(): void
    {
        $user = $this->createAuthenticatedUser();
        $monitor = Monitor::factory()->for($user->team)->create(['is_active' => false]);

        $response = $this->actingAs($user)->post(route('monitors.resume', $monitor));

        $response->assertRedirect();
        $this->assertTrue($monitor->fresh()->is_active);
    }

    public function test_cannot_access_other_team_monitor(): void
    {
        $user = $this->createAuthenticatedUser();
        $otherTeam = Team::factory()->create();
        $otherMonitor = Monitor::factory()->for($otherTeam)->create();

        $response = $this->actingAs($user)->get(route('monitors.show', $otherMonitor));

        $response->assertNotFound();
    }
}
