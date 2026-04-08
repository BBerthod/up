<?php

namespace App\Jobs\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification extends BaseNotificationJob
{
    protected function send(): void
    {
        $url = $this->channel->settings['url'] ?? null;
        if (! $url) {
            Log::warning('Webhook channel missing URL', ['channel_id' => $this->channel->id]);

            return;
        }

        Http::timeout(10)->post($url, $this->buildPayload())->throw();
    }
}
