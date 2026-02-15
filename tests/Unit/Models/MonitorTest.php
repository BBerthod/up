<?php

namespace Tests\Unit\Models;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_has_checks_relationship(): void
    {
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();
        MonitorCheck::factory()->for($monitor)->create();

        $this->assertCount(1, $monitor->checks);
        $this->assertInstanceOf(MonitorCheck::class, $monitor->checks->first());
    }

    public function test_monitor_has_incidents_relationship(): void
    {
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();
        MonitorIncident::factory()->for($monitor)->create();

        $this->assertCount(1, $monitor->incidents);
        $this->assertInstanceOf(MonitorIncident::class, $monitor->incidents->first());
    }

    public function test_monitor_has_notification_channels_relationship(): void
    {
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            $monitor->notificationChannels()
        );
    }

    public function test_active_scope_filters_active_monitors(): void
    {
        $team = Team::factory()->create();
        Monitor::factory()->for($team)->create(['is_active' => true]);
        Monitor::factory()->for($team)->create(['is_active' => false]);

        $monitors = Monitor::withoutGlobalScopes()->active()->get();

        $this->assertCount(1, $monitors);
        $this->assertTrue($monitors->first()->is_active);
    }

    public function test_inactive_scope_filters_inactive_monitors(): void
    {
        $team = Team::factory()->create();
        Monitor::factory()->for($team)->create(['is_active' => true]);
        Monitor::factory()->for($team)->create(['is_active' => false]);

        $monitors = Monitor::withoutGlobalScopes()->inactive()->get();

        $this->assertCount(1, $monitors);
        $this->assertFalse($monitors->first()->is_active);
    }
}
