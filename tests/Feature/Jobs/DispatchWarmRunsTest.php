<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchWarmRuns;
use App\Jobs\RunWarmSite;
use App\Models\WarmSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchWarmRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_for_due_sites(): void
    {
        Queue::fake();

        // Due site (active, last warmed 3 hours ago, frequency 60 min)
        $due = WarmSite::factory()->dueForWarming()->create();

        // Not-due site (active, last warmed 10 minutes ago, frequency 60 min)
        WarmSite::factory()->create([
            'is_active' => true,
            'last_warmed_at' => now()->subMinutes(10),
            'frequency_minutes' => 60,
        ]);

        // Inactive site
        WarmSite::factory()->inactive()->create();

        (new DispatchWarmRuns)->handle();

        Queue::assertPushed(RunWarmSite::class, 1);
        Queue::assertPushed(RunWarmSite::class, fn (RunWarmSite $job) => $job->warmSite->id === $due->id);
    }

    public function test_dispatches_for_never_warmed_sites(): void
    {
        Queue::fake();

        // Never warmed (null last_warmed_at) should always be due
        $neverWarmed = WarmSite::factory()->create([
            'is_active' => true,
            'last_warmed_at' => null,
            'frequency_minutes' => 60,
        ]);

        (new DispatchWarmRuns)->handle();

        Queue::assertPushed(RunWarmSite::class, 1);
        Queue::assertPushed(RunWarmSite::class, fn (RunWarmSite $job) => $job->warmSite->id === $neverWarmed->id);
    }
}
