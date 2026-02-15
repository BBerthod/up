<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;

class NotificationService
{
    public function notifyDown(Monitor $monitor, MonitorIncident $incident, MonitorCheck $check): void
    {
        $channels = $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            SendNotification::dispatch($channel, 'down', $monitor, $incident, $check);
        }
    }

    public function notifyUp(Monitor $monitor, MonitorIncident $incident, MonitorCheck $check): void
    {
        $channels = $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            SendNotification::dispatch($channel, 'up', $monitor, $incident, $check);
        }
    }
}
