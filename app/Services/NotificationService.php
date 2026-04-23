<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Enums\WarmRunStatus;
use App\Jobs\Notifications\SendDiscordNotification;
use App\Jobs\Notifications\SendEmailNotification;
use App\Jobs\Notifications\SendPushNotification;
use App\Jobs\Notifications\SendSlackNotification;
use App\Jobs\Notifications\SendTelegramNotification;
use App\Jobs\Notifications\SendWebhookNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\NotificationChannel;
use App\Models\WarmRun;
use App\Models\WarmSite;
use App\Notifications\WarmRunFailedNotification;
use App\Notifications\WarmSiteDisabledNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyDown(Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check = null): void
    {
        $cooldownMinutes = config('monitoring.notification_cooldown_minutes', 5);

        $channels = $monitor->relationLoaded('notificationChannels')
            ? $monitor->notificationChannels->where('is_active', true)
            : $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            // Per-channel cooldown: each channel gets its own independent key so a
            // slow/misconfigured channel does not suppress notifications on others.
            $cooldownKey = "notify:{$monitor->id}:{$channel->id}:down";

            if (Cache::has($cooldownKey)) {
                continue;
            }

            Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));
            $this->dispatchForChannel($channel, 'down', $monitor, $incident, $check);
        }
    }

    public function notifyUp(Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check = null): void
    {
        $cooldownMinutes = config('monitoring.notification_cooldown_minutes', 5);

        $channels = $monitor->relationLoaded('notificationChannels')
            ? $monitor->notificationChannels->where('is_active', true)
            : $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            // Per-channel cooldown for "up" events prevents flap spam.
            // We do NOT reset the "down" cooldown here — that key expires naturally,
            // ensuring a fresh outage always triggers a new "down" notification.
            $cooldownKey = "notify:{$monitor->id}:{$channel->id}:up";

            if (Cache::has($cooldownKey)) {
                continue;
            }

            Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));
            $this->dispatchForChannel($channel, 'up', $monitor, $incident, $check);
        }
    }

    /**
     * Notify on warming failure with transition detection and circuit breaker.
     *
     * - Notifies only on the first failure after a successful run (success→fail transition).
     * - After CIRCUIT_BREAKER_THRESHOLD consecutive failures, auto-disables the site
     *   and sends a single "auto-disabled" notification. No further notifications after that.
     * - A 1-hour dedup cache key acts as a safety net in all cases.
     */
    public function notifyWarmingFailed(WarmSite $warmSite, WarmRun $warmRun): void
    {
        // Safety net: never send more than once per hour per site.
        $dedupKey = "notify:warming:{$warmSite->id}:failed";
        if (Cache::has($dedupKey)) {
            return;
        }

        $consecutiveFailures = $this->countConsecutiveFailures($warmSite, $warmRun);

        $threshold = config('warming.circuit_breaker_threshold', 5);

        // Circuit breaker: disable site and send the final "auto-disabled" email.
        if ($consecutiveFailures >= $threshold) {
            $warmSite->update(['is_active' => false]);

            Log::warning('WarmSite auto-disabled after consecutive failures', [
                'warm_site_id' => $warmSite->id,
                'consecutive_failures' => $consecutiveFailures,
            ]);

            Cache::put($dedupKey, true, now()->addHour());

            $owner = $warmSite->team->users()->first();
            if ($owner) {
                $owner->notify(new WarmSiteDisabledNotification($warmSite, $consecutiveFailures));
            }

            return;
        }

        // Transition guard: only notify on the first failure after a success.
        // If the previous non-current run was also a FAILED run, this is already a
        // known ongoing failure — stay silent to avoid spam.
        if (! $this->isSuccessToFailTransition($warmSite, $warmRun)) {
            return;
        }

        Cache::put($dedupKey, true, now()->addHour());

        $owner = $warmSite->team->users()->first();
        if ($owner) {
            $owner->notify(new WarmRunFailedNotification($warmSite, $warmRun));
        }
    }

    /**
     * Count how many consecutive FAILED runs precede (and include) the given run,
     * ordered by started_at DESC. Scans at most the last 20 runs to avoid full-table scans.
     */
    private function countConsecutiveFailures(WarmSite $warmSite, WarmRun $warmRun): int
    {
        $recentRuns = WarmRun::where('warm_site_id', $warmSite->id)
            ->whereIn('status', [WarmRunStatus::FAILED->value, WarmRunStatus::COMPLETED->value])
            ->orderByDesc('started_at')
            ->limit(20)
            ->get(['id', 'status']);

        $count = 0;

        foreach ($recentRuns as $run) {
            if ($run->status === WarmRunStatus::FAILED) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    /**
     * Returns true when the run just before the current one (excluding RUNNING status)
     * was COMPLETED — i.e., we are at the transition point success→fail.
     */
    private function isSuccessToFailTransition(WarmSite $warmSite, WarmRun $warmRun): bool
    {
        $previousRun = WarmRun::where('warm_site_id', $warmSite->id)
            ->where('id', '!=', $warmRun->id)
            ->whereIn('status', [WarmRunStatus::FAILED->value, WarmRunStatus::COMPLETED->value])
            ->orderByDesc('started_at')
            ->limit(1)
            ->first(['status']);

        // No previous run at all → first failure ever, treat as transition.
        if ($previousRun === null) {
            return true;
        }

        return $previousRun->status === WarmRunStatus::COMPLETED;
    }

    private function dispatchForChannel(NotificationChannel $channel, string $event, Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check): void
    {
        $jobClass = match ($channel->type) {
            ChannelType::EMAIL => SendEmailNotification::class,
            ChannelType::WEBHOOK => SendWebhookNotification::class,
            ChannelType::SLACK => SendSlackNotification::class,
            ChannelType::DISCORD => SendDiscordNotification::class,
            ChannelType::PUSH => SendPushNotification::class,
            ChannelType::TELEGRAM => SendTelegramNotification::class,
        };

        $jobClass::dispatch($channel, $event, $monitor, $incident, $check);
    }
}
