<?php

namespace App\Jobs\Notifications;

use Illuminate\Support\Facades\Http;

class SendDiscordNotification extends BaseNotificationJob
{
    protected function send(): void
    {
        $color = $this->event === 'down' ? 15158332 : 3066993;
        $statusText = $this->event === 'down' ? 'Down' : 'Up';

        Http::timeout(10)->post($this->channel->settings['webhook_url'], [
            'embeds' => [[
                'title' => "[Up] {$this->monitor->name} is {$statusText}",
                'url' => $this->monitor->url,
                'color' => $color,
                'fields' => array_filter([
                    ['name' => 'URL', 'value' => $this->monitor->url, 'inline' => false],
                    $this->check ? ['name' => 'Status Code', 'value' => (string) $this->check->status_code, 'inline' => true] : null,
                    $this->check ? ['name' => 'Response Time', 'value' => "{$this->check->response_time_ms}ms", 'inline' => true] : null,
                    ['name' => 'Cause', 'value' => ucfirst(str_replace('_', ' ', $this->incident->cause->value)), 'inline' => true],
                ]),
                'timestamp' => now()->toIso8601String(),
                'footer' => ['text' => config('app.name')],
            ]],
        ])->throw();
    }
}
