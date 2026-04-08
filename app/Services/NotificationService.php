<?php

namespace App\Services;

use App\Enums\ChannelType;
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

        $channels = $monitor->relationLoaded('notificationChannels')
            ? $monitor->notificationChannels->where('is_active', true)
            : $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            $this->dispatchForChannel($channel, 'down', $monitor, $incident, $check);
        }
    }

    public function notifyUp(Monitor $monitor, MonitorIncident $incident, ?MonitorCheck $check = null): void
    {
        Cache::forget("notify:{$monitor->id}:down");

        $channels = $monitor->relationLoaded('notificationChannels')
            ? $monitor->notificationChannels->where('is_active', true)
            : $monitor->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            $this->dispatchForChannel($channel, 'up', $monitor, $incident, $check);
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
