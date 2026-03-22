<?php

namespace App\Jobs;

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
        $sites = WarmSite::withoutGlobalScopes()
            ->dueForWarming()
            ->get();

        foreach ($sites as $site) {
            RunWarmSite::dispatch($site);
        }
    }
}
