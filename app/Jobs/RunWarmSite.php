<?php

namespace App\Jobs;

use App\Enums\WarmRunStatus;
use App\Models\WarmRun;
use App\Models\WarmRunUrl;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunWarmSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [300, 3600];

    public function __construct(public WarmSite $warmSite)
    {
        $this->onQueue('monitors');
    }

    public function retryUntil(): \DateTime
    {
        $maxUrls = max(1, (int) $this->warmSite->max_urls);
        $seconds = max(120, min(7200, $maxUrls * 15));

        return now()->addSeconds($seconds);
    }

    public function handle(WarmingService $warmingService): void
    {
        $lock = Cache::lock("warming:{$this->warmSite->id}", 120);

        if (! $lock->get()) {
            return;
        }

        $warmRun = null;

        try {
            $warmRun = WarmRun::create([
                'warm_site_id' => $this->warmSite->id,
                'status' => WarmRunStatus::RUNNING,
                'urls_total' => 0,
                'urls_hit' => 0,
                'urls_miss' => 0,
                'urls_error' => 0,
                'avg_response_ms' => 0,
                'started_at' => now(),
            ]);

            $urls = $warmingService->resolveUrls($this->warmSite);
            $customHeaders = $this->warmSite->custom_headers ?? [];

            $hits = 0;
            $misses = 0;
            $errors = 0;
            $totalResponseMs = 0;
            $consecutiveErrors = 0;
            $errorMessage = null;

            foreach ($urls as $index => $url) {
                if ($index > 0) {
                    usleep(1_000_000);
                }

                $result = $warmingService->warmUrl($url, $customHeaders);

                $totalResponseMs += $result->responseTimeMs;

                if ($result->isError()) {
                    $errors++;
                    $consecutiveErrors++;
                } elseif ($result->isHit()) {
                    $hits++;
                    $consecutiveErrors = 0;
                } else {
                    $misses++;
                    $consecutiveErrors = 0;
                }

                WarmRunUrl::create([
                    'warm_run_id' => $warmRun->id,
                    'url' => $result->url,
                    'status_code' => $result->statusCode,
                    'cache_status' => $result->cacheStatus,
                    'response_time_ms' => $result->responseTimeMs,
                    'error_message' => $result->errorMessage,
                ]);

                if ($result->statusCode === 429) {
                    sleep(5);
                }

                if ($consecutiveErrors >= 3) {
                    $errorMessage = "Stopped early: 3 consecutive errors. Last error: {$result->errorMessage}";
                    break;
                }
            }

            $total = $hits + $misses + $errors;
            $avgResponseMs = $total > 0 ? (int) round($totalResponseMs / $total) : 0;

            $warmRun->update([
                'urls_total' => $total,
                'urls_hit' => $hits,
                'urls_miss' => $misses,
                'urls_error' => $errors,
                'avg_response_ms' => $avgResponseMs,
                'status' => WarmRunStatus::COMPLETED,
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);

            $this->warmSite->update(['last_warmed_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunWarmSite job failed', [
            'warm_site_id' => $this->warmSite->id,
            'warm_site_name' => $this->warmSite->name,
            'error' => $e->getMessage(),
        ]);
    }
}
