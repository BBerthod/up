<?php

namespace Tests\Feature\Jobs;

use App\Enums\WarmRunStatus;
use App\Jobs\RunWarmSite;
use App\Models\WarmRun;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWarmSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_warm_run_with_stats(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('OK', 200, [
                'cf-cache-status' => 'HIT',
            ]),
            'https://example.com/about' => Http::response('About', 200, [
                'cf-cache-status' => 'MISS',
            ]),
        ]);

        $site = WarmSite::factory()->create([
            'urls' => [
                'https://example.com/',
                'https://example.com/about',
            ],
            'max_urls' => 50,
            'last_warmed_at' => null,
        ]);

        (new RunWarmSite($site))->handle(app(WarmingService::class));

        $this->assertDatabaseCount('warm_runs', 1);

        $run = WarmRun::first();
        $this->assertEquals(WarmRunStatus::COMPLETED, $run->status);
        $this->assertEquals(2, $run->urls_total);
        $this->assertEquals(1, $run->urls_hit);
        $this->assertEquals(1, $run->urls_miss);
        $this->assertEquals(0, $run->urls_error);
        $this->assertNotNull($run->completed_at);
        $this->assertNull($run->error_message);

        $site->refresh();
        $this->assertNotNull($site->last_warmed_at);
    }

    public function test_handles_errors_gracefully(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('OK', 200, [
                'cf-cache-status' => 'HIT',
            ]),
            'https://example.com/slow' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $site = WarmSite::factory()->create([
            'urls' => [
                'https://example.com/',
                'https://example.com/slow',
            ],
            'max_urls' => 50,
            'last_warmed_at' => null,
        ]);

        (new RunWarmSite($site))->handle(app(WarmingService::class));

        $run = WarmRun::first();
        $this->assertEquals(WarmRunStatus::COMPLETED, $run->status);
        $this->assertEquals(2, $run->urls_total);
        $this->assertEquals(1, $run->urls_hit);
        $this->assertEquals(0, $run->urls_miss);
        $this->assertEquals(1, $run->urls_error);
    }

    public function test_skips_if_lock_unavailable(): void
    {
        $site = WarmSite::factory()->create();

        // Acquire the lock manually to simulate another process holding it
        $lock = Cache::lock("warming:{$site->id}", 120);
        $lock->get();

        try {
            (new RunWarmSite($site))->handle(app(WarmingService::class));

            $this->assertDatabaseCount('warm_runs', 0);
        } finally {
            $lock->release();
        }
    }
}
