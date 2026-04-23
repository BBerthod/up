<?php

namespace Tests\Feature\Services;

use App\Enums\CheckStatus;
use App\Enums\IncidentCause;
use App\Enums\MonitorMethod;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Services\CheckService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private CheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CheckService::class);
    }

    public function test_successful_check_creates_up_record(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'keyword' => null,
        ]);

        Http::fake([
            'example.com' => Http::response('Hello World', 200),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::UP, $check->status);
        $this->assertEquals(200, $check->status_code);
        $this->assertNotNull($check->response_time_ms);
        $this->assertDatabaseHas('monitor_checks', [
            'monitor_id' => $monitor->id,
            'status' => CheckStatus::UP->value,
        ]);
    }

    public function test_failed_status_code_creates_down_record(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'keyword' => null,
        ]);

        Http::fake([
            'example.com' => Http::response('Error', 500),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::DOWN, $check->status);
        $this->assertEquals(500, $check->status_code);

        $this->assertDatabaseHas('monitor_incidents', [
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::STATUS_CODE->value,
            'resolved_at' => null,
        ]);
    }

    public function test_keyword_missing_creates_down_record(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'keyword' => 'foobar',
        ]);

        Http::fake([
            'example.com' => Http::response('hello', 200),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::DOWN, $check->status);

        $this->assertDatabaseHas('monitor_incidents', [
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::KEYWORD->value,
            'resolved_at' => null,
        ]);
    }

    public function test_keyword_present_creates_up_record(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'keyword' => 'hello',
        ]);

        Http::fake([
            'example.com' => Http::response('hello world', 200),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::UP, $check->status);
        $this->assertEquals(200, $check->status_code);
    }

    public function test_connection_timeout_creates_down_record(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
        ]);

        Http::fake([
            'example.com' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::DOWN, $check->status);
        $this->assertNull($check->status_code);
        $this->assertNotNull($check->error_message);
        $this->assertStringContainsString('timed out', strtolower($check->error_message));
    }

    public function test_state_change_up_to_down_creates_incident(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
        ]);

        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => CheckStatus::UP,
            'response_time_ms' => 100,
            'status_code' => 200,
            'checked_at' => now()->subMinute(),
        ]);

        Http::fake([
            'example.com' => Http::response('Error', 500),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::DOWN, $check->status);

        $this->assertDatabaseHas('monitor_incidents', [
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::STATUS_CODE->value,
            'resolved_at' => null,
        ]);
    }

    public function test_state_change_down_to_up_resolves_incident(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
        ]);

        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => CheckStatus::DOWN,
            'response_time_ms' => 100,
            'status_code' => 500,
            'checked_at' => now()->subMinute(),
        ]);

        $incident = MonitorIncident::create([
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::STATUS_CODE,
            'started_at' => now()->subMinute(),
        ]);

        Http::fake([
            'example.com' => Http::response('OK', 200),
        ]);

        $check = $this->service->check($monitor);

        $this->assertEquals(CheckStatus::UP, $check->status);

        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_threshold_exceeded_creates_timeout_incident(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'critical_threshold_ms' => 500,
        ]);

        foreach ([600, 700, 800] as $i => $ms) {
            MonitorCheck::create([
                'monitor_id' => $monitor->id,
                'status' => CheckStatus::UP,
                'response_time_ms' => $ms,
                'status_code' => 200,
                'checked_at' => now()->subMinutes(4 - $i),
            ]);
        }

        Http::fake(['example.com' => Http::response('OK', 200, [])]);

        // The fake response will be fast, but checkThresholds reads from DB
        // so we seed a slow response for the new check too
        $monitor->checks()->latest('checked_at')->first()->update(['response_time_ms' => 900]);

        // Simulate: 3 consecutive slow DB checks trigger the incident
        // We do it by calling check() and checking the incident was created
        $this->assertDatabaseMissing('monitor_incidents', ['monitor_id' => $monitor->id]);

        // Manually create an additional slow check to trigger threshold
        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => CheckStatus::UP,
            'response_time_ms' => 950,
            'status_code' => 200,
            'checked_at' => now(),
        ]);

        // Invoke threshold check directly via reflection
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('checkThresholds');
        $method->setAccessible(true);

        $check = $monitor->checks()->latest('checked_at')->first();
        $method->invoke($this->service, $monitor, $check);

        $this->assertDatabaseHas('monitor_incidents', [
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::TIMEOUT->value,
            'resolved_at' => null,
        ]);
    }

    public function test_threshold_recovery_resolves_active_incident(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'critical_threshold_ms' => 500,
        ]);

        // Active threshold incident (monitor was slow but UP)
        $incident = MonitorIncident::create([
            'monitor_id' => $monitor->id,
            'cause' => IncidentCause::TIMEOUT,
            'started_at' => now()->subHour(),
        ]);

        // 3 recent checks now back under threshold
        foreach ([100, 120, 90] as $i => $ms) {
            MonitorCheck::create([
                'monitor_id' => $monitor->id,
                'status' => CheckStatus::UP,
                'response_time_ms' => $ms,
                'status_code' => 200,
                'checked_at' => now()->subMinutes(3 - $i),
            ]);
        }

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('checkThresholds');
        $method->setAccessible(true);

        $check = $monitor->checks()->latest('checked_at')->first();
        $method->invoke($this->service, $monitor, $check);

        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_updates_monitor_last_checked_at(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'last_checked_at' => null,
        ]);

        Http::fake([
            'example.com' => Http::response('OK', 200),
        ]);

        $this->service->check($monitor);

        $monitor->refresh();
        $this->assertNotNull($monitor->last_checked_at);
    }

    public function test_notification_sent_only_after_threshold_failures(): void
    {
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->expects('notifyDown')->once();
        $notificationService->expects('notifyUp')->never();

        // Re-resolve CheckService so it gets the mocked NotificationService.
        $this->service = app(CheckService::class);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'alert_after_failures' => 3,
        ]);

        Http::fake(['example.com' => Http::response('Internal Server Error', 500)]);

        // 1st failure: opens incident, consecutive count = 1 (below threshold 3).
        $this->service->check($monitor);

        // 2nd failure: consecutive count = 2, still below threshold.
        $this->service->check($monitor);

        // 3rd failure: consecutive count = 3 === threshold, notification fires.
        $this->service->check($monitor);
    }
}
