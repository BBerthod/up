<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\WarmRun;
use App\Models\WarmSite;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function notifyDown(Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check = null): void
    {
        $cooldownKey = "notify:{$monitor->id}:down";
        $cooldownMinutes = config('monitoring.notification_cooldown_minutes', 5);

        if (Cache::has($cooldownKey)) {
            return;
        }

        Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));

        $channels = $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            SendNotification::dispatch($channel, 'down', $monitor, $incident, $check);
        }
    }

    public function notifyUp(Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check = null): void
    {
        Cache::forget("notify:{$monitor->id}:down");

        $channels = $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            SendNotification::dispatch($channel, 'up', $monitor, $incident, $check);
        }
    }

    public function notifyWarmingFailed(WarmSite $warmSite, WarmRun $warmRun): void
    {
        $key = "notify:warming:{$warmSite->id}:failed";

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, true, now()->addHour());

        $owner = $warmSite->team->users()->first();
        if ($owner) {
            $owner->notify(new \App\Notifications\WarmRunFailedNotification($warmSite, $warmRun));
        }
    }
}
