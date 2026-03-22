<?php

namespace App\Jobs;

use App\Models\WarmRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneWarmRuns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $retentionDays = (int) config('warming.retention_days', 180);

        $deleted = WarmRun::where('created_at', '<', now()->subDays($retentionDays))->delete();

        if ($deleted > 0) {
            Log::info('PruneWarmRuns: deleted old warm runs', [
                'count' => $deleted,
                'retention_days' => $retentionDays,
            ]);
        }
    }
}
