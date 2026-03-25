<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Mail\MonitorAlertMail;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\NotificationChannel;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public NotificationChannel $channel,
        public string $event,
        public Monitor $monitor,
        public MonitorIncident $incident,
        public ?MonitorCheck $check = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $payload = $this->buildPayload();

        match ($this->channel->type) {
            ChannelType::EMAIL => $this->sendEmail($payload),
            ChannelType::WEBHOOK => $this->sendWebhook($payload),
            ChannelType::SLACK => $this->sendSlack(),
            ChannelType::DISCORD => $this->sendDiscord(),
            ChannelType::PUSH => $this->sendPush(),
            ChannelType::TELEGRAM => $this->sendTelegram(),
        };
    }

    public function failed(Throwable $e): void
    {
        Log::error('Notification delivery failed', [
            'channel_id' => $this->channel->id,
            'channel_type' => $this->channel->type->value,
            'event' => $this->event,
            'monitor_id' => $this->monitor->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function buildPayload(): array
    {
        return [
            'monitor' => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
                'url' => $this->monitor->url,
            ],
            'event' => "monitor.{$this->event}",
            'incident' => [
                'id' => $this->incident->id,
                'cause' => $this->incident->cause->value,
                'started_at' => $this->incident->started_at->toIso8601String(),
            ],
            'check' => $this->check ? [
                'status_code' => $this->check->status_code,
                'response_time_ms' => $this->check->response_time_ms,
            ] : null,
        ];
    }

    private function sendEmail(array $payload): void
    {
        Mail::to($this->channel->settings['recipients'])
            ->send(new MonitorAlertMail($this->event, $payload));
    }

    private function sendWebhook(array $payload): void
    {
        $url = $this->channel->settings['url'] ?? null;
        if (! $url) {
            Log::warning('Webhook channel missing URL', ['channel_id' => $this->channel->id]);

            return;
        }

        Http::timeout(10)->post($url, $payload)->throw();
    }

    private function sendSlack(): void
    {
        $webhookUrl = $this->channel->settings['webhook_url'] ?? null;
        if (! $webhookUrl) {
            Log::warning('Slack channel missing webhook_url', ['channel_id' => $this->channel->id]);

            return;
        }

        $color = $this->event === 'down' ? '#DC2626' : '#16A34A';
        $statusText = $this->event === 'down' ? 'Down' : 'Up';

        Http::timeout(10)->post($webhookUrl, [
            'attachments' => [[
                'color' => $color,
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => ['type' => 'plain_text', 'text' => "[Up] {$this->monitor->name} is {$statusText}", 'emoji' => true],
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*URL:*\n{$this->monitor->url}"],
                            ['type' => 'mrkdwn', 'text' => "*Cause:*\n{$this->incident->cause->value}"],
                        ],
                    ],
                    ...($this->check ? [[
                        'type' => 'section',
                        'fields' => [
                            ['type' => 'mrkdwn', 'text' => "*Status Code:*\n{$this->check->status_code}"],
                            ['type' => 'mrkdwn', 'text' => "*Response Time:*\n{$this->check->response_time_ms}ms"],
                        ],
                    ]] : []),
                ],
            ]],
        ])->throw();
    }

    private function sendPush(): void
    {
        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.webpush.vapid.public_key'),
                'privateKey' => config('services.webpush.vapid.private_key'),
            ],
        ];

        try {
            $webPush = new WebPush($auth);
        } catch (\Exception $e) {
            Log::error('WebPush initialization failed', ['error' => $e->getMessage()]);

            return;
        }

        $statusText = $this->event === 'down' ? 'Down' : 'Up';

        $payload = json_encode([
            'title' => "[Up] {$this->monitor->name} is {$statusText}",
            'body' => "URL: {$this->monitor->url} - Cause: ".ucfirst(str_replace('_', ' ', $this->incident->cause->value)),
            'data' => ['url' => config('app.url')."/monitors/{$this->monitor->id}"],
        ]);

        $userIds = $this->channel->team->users()->pluck('id');
        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();

        foreach ($subscriptions as $dbSubscription) {
            $subscription = Subscription::create([
                'endpoint' => $dbSubscription->endpoint,
                'publicKey' => $dbSubscription->p256dh,
                'authToken' => $dbSubscription->auth,
            ]);

            $report = $webPush->sendOneNotification($subscription, $payload);

            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $dbSubscription->delete();
                Log::info('Deleted expired push subscription', ['endpoint' => $dbSubscription->endpoint]);
            } else {
                Log::error('Push notification failed', [
                    'endpoint' => $dbSubscription->endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private function sendTelegram(): void
    {
        $botToken = $this->channel->settings['bot_token'] ?? null;
        $chatId = $this->channel->settings['chat_id'] ?? null;

        if (! $botToken || ! $chatId) {
            Log::warning('Telegram channel missing settings', ['channel_id' => $this->channel->id]);

            return;
        }

        $statusEmoji = $this->event === 'down' ? "\u{1F534}" : "\u{1F7E2}";
        $statusText = $this->event === 'down' ? 'Down' : 'Up';
        $cause = ucfirst(str_replace('_', ' ', $this->incident->cause->value));
        $monitorName = htmlspecialchars($this->monitor->name, ENT_QUOTES, 'UTF-8');
        $monitorUrl = htmlspecialchars($this->monitor->url, ENT_QUOTES, 'UTF-8');

        $text = "<b>{$statusEmoji} [Up] {$monitorName} is {$statusText}</b>\n\n"
            ."<b>URL:</b> {$monitorUrl}\n"
            .($this->check ? "<b>Status Code:</b> {$this->check->status_code}\n<b>Response Time:</b> {$this->check->response_time_ms}ms\n" : '')
            ."<b>Cause:</b> {$cause}";

        $response = Http::timeout(10)->post(
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]
        );

        if ($response->failed()) {
            Log::error('Telegram API error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        $response->throw();
    }

    private function sendDiscord(): void
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
