<?php

namespace App\Jobs;

use App\Enums\WarmRunStatus;
use App\Models\WarmRun;
use App\Models\WarmSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWarmRuns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('monitors');
    }

    public function handle(): void
    {
        // Clean up stale runs (stuck in "running" for > 10 min, likely killed by deploy/restart)
        WarmRun::where('status', WarmRunStatus::RUNNING)
            ->where('started_at', '<', now()->subMinutes(10))
            ->update([
                'status' => WarmRunStatus::FAILED,
                'error_message' => 'Stale run: marked failed after 10 minutes (likely killed by deploy or timeout)',
                'completed_at' => now(),
            ]);

        $sites = WarmSite::withoutGlobalScopes()
            ->dueForWarming()
            ->whereDoesntHave('warmRuns', fn ($q) => $q->where('status', WarmRunStatus::RUNNING))
            ->get();

        foreach ($sites as $site) {
            RunWarmSite::dispatch($site);
        }
    }
}
