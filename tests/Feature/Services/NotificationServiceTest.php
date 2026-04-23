<?php

namespace Tests\Feature\Services;

use App\Jobs\Notifications\SendEmailNotification;
use App\Jobs\Notifications\SendWebhookNotification;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    // ──────────────────────────────────────────────────
    // notifyDown — per-channel cooldown
    // ──────────────────────────────────────────────────

    public function test_notify_down_dispatches_job_per_active_channel(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();
        $channel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach($channel);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        $this->service->notifyDown($monitor, $incident);

        Queue::assertPushed(SendEmailNotification::class, 1);
    }

    public function test_notify_down_respects_per_channel_cooldown(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channelA = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $channelB = NotificationChannel::factory()->webhook()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach([$channelA->id, $channelB->id]);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // First call → 2 jobs dispatched (one per channel).
        $this->service->notifyDown($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 1);
        Queue::assertPushedTimes(SendWebhookNotification::class, 1);

        // Second call within the cooldown window → 0 new jobs.
        $this->service->notifyDown($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 1);
        Queue::assertPushedTimes(SendWebhookNotification::class, 1);
    }

    public function test_notify_down_with_cache_miss_dispatches_exactly_once_per_channel(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach($channel);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // First call with empty cache → dispatches.
        $this->service->notifyDown($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 1);

        // Manually clear the cooldown cache (simulates TTL expiry).
        Cache::flush();

        // After cache clear → dispatches again (fresh cooldown cycle).
        $this->service->notifyDown($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 2);
    }

    public function test_notify_down_skips_inactive_channels(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $activeChannel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $inactiveChannel = NotificationChannel::factory()->for($team)->create(['is_active' => false]);
        $monitor->notificationChannels()->attach([$activeChannel->id, $inactiveChannel->id]);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        $this->service->notifyDown($monitor, $incident);

        Queue::assertPushedTimes(SendEmailNotification::class, 1);
    }

    // ──────────────────────────────────────────────────
    // notifyUp — independent cooldown
    // ──────────────────────────────────────────────────

    public function test_notify_up_has_independent_cooldown_from_notify_down(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach($channel);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // Trigger down notification → sets down cooldown key.
        $this->service->notifyDown($monitor, $incident);

        // Up notification uses a different key; should dispatch independently.
        $this->service->notifyUp($monitor, $incident);

        // Both should have fired once.
        Queue::assertPushedTimes(SendEmailNotification::class, 2);
    }

    public function test_notify_up_does_not_reset_down_cooldown(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach($channel);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // Down → sets down cooldown.
        $this->service->notifyDown($monitor, $incident);

        // Up → sets up cooldown, must NOT affect the down cooldown key.
        $this->service->notifyUp($monitor, $incident);

        // Second down call → still suppressed by the original down cooldown.
        $this->service->notifyDown($monitor, $incident);

        // Total: 1 down + 1 up = 2 jobs (third call suppressed).
        Queue::assertPushedTimes(SendEmailNotification::class, 2);
    }

    public function test_notify_up_is_suppressed_within_cooldown_window(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channel = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach($channel);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // First up notification → dispatches.
        $this->service->notifyUp($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 1);

        // Second up call within cooldown → suppressed.
        $this->service->notifyUp($monitor, $incident);
        Queue::assertPushedTimes(SendEmailNotification::class, 1);
    }

    // ──────────────────────────────────────────────────
    // Per-channel isolation
    // ──────────────────────────────────────────────────

    public function test_each_channel_has_independent_down_cooldown_key(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        $monitor = Monitor::factory()->for($team)->create();

        $channelA = NotificationChannel::factory()->for($team)->create(['is_active' => true]);
        $channelB = NotificationChannel::factory()->webhook()->for($team)->create(['is_active' => true]);
        $monitor->notificationChannels()->attach([$channelA->id, $channelB->id]);

        $incident = MonitorIncident::factory()->for($monitor)->create();

        // First call: both channels notified.
        $this->service->notifyDown($monitor, $incident);

        // Manually expire only channel A's cooldown.
        Cache::forget("notify:{$monitor->id}:{$channelA->id}:down");

        // Second call: only channel A dispatches again.
        $this->service->notifyDown($monitor, $incident);

        Queue::assertPushedTimes(SendEmailNotification::class, 2);
        Queue::assertPushedTimes(SendWebhookNotification::class, 1);
    }
}
