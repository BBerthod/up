<?php

namespace App\Services;

use App\DTOs\FunctionalResult;
use App\Enums\FunctionalCheckStatus;
use App\Enums\FunctionalCheckType;
use App\Jobs\SendFunctionalNotification;
use App\Models\FunctionalCheck;
use App\Models\FunctionalCheckResult;
use App\Services\Checkers\Functional\ContentChecker;
use App\Services\Checkers\Functional\RedirectChecker;
use App\Services\Checkers\Functional\RobotsChecker;
use App\Services\Checkers\Functional\SitemapChecker;

class FunctionalCheckService
{
    public function run(FunctionalCheck $check): FunctionalCheckResult
    {
        $result = match ($check->type) {
            FunctionalCheckType::CONTENT => (new ContentChecker)->check($check),
            FunctionalCheckType::REDIRECT => (new RedirectChecker)->check($check),
            FunctionalCheckType::SITEMAP => (new SitemapChecker)->check($check),
            FunctionalCheckType::ROBOTS_TXT => (new RobotsChecker)->check($check),
        };

        $status = $result->passed
            ? FunctionalCheckStatus::PASSED
            : FunctionalCheckStatus::FAILED;

        $record = FunctionalCheckResult::create([
            'functional_check_id' => $check->id,
            'status' => $status,
            'duration_ms' => $result->durationMs,
            'details' => $result->details,
            'error_message' => $result->errorMessage,
            'checked_at' => now(),
        ]);

        $check->update([
            'last_checked_at' => now(),
            'last_status' => $status,
        ]);

        if (! $result->passed) {
            $this->notifyFailure($check, $result);
        }

        return $record;
    }

    private function notifyFailure(FunctionalCheck $check, FunctionalResult $result): void
    {
        $channels = $check->monitor->notificationChannels()
            ->where('is_active', true)
            ->get();

        foreach ($channels as $channel) {
            SendFunctionalNotification::dispatch($channel, $check, $result);
        }
    }
}
