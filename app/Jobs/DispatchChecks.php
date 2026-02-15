<?php

namespace App\Jobs;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('monitors');
    }

    public function handle(): void
    {
        $monitors = Monitor::withoutGlobalScopes()
            ->active()
            ->dueForCheck()
            ->get();

        foreach ($monitors as $monitor) {
            RunCheck::dispatch($monitor);
        }
    }
}
