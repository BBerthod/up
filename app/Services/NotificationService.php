<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
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
}
