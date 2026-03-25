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

    public int $timeout = 300;

    public function handle(): void
    {
        $retentionDays = (int) config('warming.retention_days', 180);
        $cutoff = now()->subDays($retentionDays);
        $total = 0;

        do {
            $deleted = WarmRun::where('created_at', '<', $cutoff)
                ->limit(500)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total > 0) {
            Log::info('PruneWarmRuns: deleted old warm runs', [
                'count' => $total,
                'retention_days' => $retentionDays,
            ]);
        }
    }
}
