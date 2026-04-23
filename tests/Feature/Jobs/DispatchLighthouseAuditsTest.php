<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchLighthouseAudits;
use App\Jobs\RunLighthouseAudit;
use App\Models\Monitor;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchLighthouseAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_run_lighthouse_audit_for_active_http_monitors(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        Monitor::factory()->count(3)->for($team)->create([
            'type' => 'http',
            'is_active' => true,
        ]);

        (new DispatchLighthouseAudits)->handle();

        Queue::assertPushed(RunLighthouseAudit::class, 3);
    }

    public function test_does_not_dispatch_for_inactive_http_monitors(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        Monitor::factory()->inactive()->for($team)->create(['type' => 'http']);

        (new DispatchLighthouseAudits)->handle();

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_for_non_http_monitors(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        Monitor::factory()->ping()->for($team)->create(['is_active' => true]);
        Monitor::factory()->port()->for($team)->create(['is_active' => true]);

        (new DispatchLighthouseAudits)->handle();

        Queue::assertNothingPushed();
    }

    public function test_dispatches_only_http_monitors_in_mixed_set(): void
    {
        Queue::fake();

        $team = Team::factory()->create();

        Monitor::factory()->count(2)->for($team)->create([
            'type' => 'http',
            'is_active' => true,
        ]);

        Monitor::factory()->ping()->for($team)->create(['is_active' => true]);
        Monitor::factory()->inactive()->for($team)->create(['type' => 'http']);

        (new DispatchLighthouseAudits)->handle();

        Queue::assertPushed(RunLighthouseAudit::class, 2);
    }

    public function test_dispatches_correct_monitor_to_audit_job(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create([
            'type' => 'http',
            'is_active' => true,
        ]);

        (new DispatchLighthouseAudits)->handle();

        Queue::assertPushed(RunLighthouseAudit::class, function (RunLighthouseAudit $job) use ($monitor) {
            return $job->monitor->id === $monitor->id;
        });
    }
}
