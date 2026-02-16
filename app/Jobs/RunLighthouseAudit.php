<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\LighthouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunLighthouseAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public Monitor $monitor)
    {
        $this->onQueue('monitors');
    }

    public function handle(LighthouseService $lighthouseService): void
    {
        $lighthouseService->audit($this->monitor);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunLighthouseAudit job failed', [
            'monitor_id' => $this->monitor->id,
            'error' => $e->getMessage(),
        ]);
    }
}
