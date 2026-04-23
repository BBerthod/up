<?php

namespace App\Jobs\Notifications;

use App\Exceptions\UnsafeUrlException;
use App\Models\PushSubscription;
use App\Support\UrlSafetyValidator;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPushNotification extends BaseNotificationJob
{
    public int $timeout = 300;

    protected function send(): void
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
            // Guard against SSRF via a tampered push endpoint stored in the DB.
            try {
                UrlSafetyValidator::assertSafe($dbSubscription->endpoint);
            } catch (UnsafeUrlException $e) {
                Log::warning('Push subscription endpoint blocked by SSRF guard', [
                    'endpoint' => $dbSubscription->endpoint,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

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
}
