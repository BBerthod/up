<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $query = Monitor::query()
            ->with(['checks' => fn ($q) => $q->latest('checked_at')->limit(1)])
            ->withCount(['incidents as active_incidents_count' => fn ($q) => $q->whereNull('resolved_at')]);

        if ($status === 'paused') {
            $query->inactive();
        }

        $monitors = $query->latest()->get()->map(function ($monitor) {
            $latestCheck = $monitor->checks->first();

            $uptime24h = $monitor->checks()
                ->where('checked_at', '>=', now()->subDay())
                ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
                ->value('uptime');

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'is_active' => $monitor->is_active,
                'latest_check' => $latestCheck ? [
                    'status' => $latestCheck->status->value,
                    'response_time_ms' => $latestCheck->response_time_ms,
                    'checked_at' => $latestCheck->checked_at?->toIso8601String(),
                ] : null,
                'uptime_24h' => $uptime24h !== null ? (float) $uptime24h : null,
                'active_incidents_count' => $monitor->active_incidents_count,
            ];
        });

        // Filter after mapping to allow status-based filtering on latest check
        if ($status === 'up') {
            $monitors = $monitors->filter(fn ($m) => $m['is_active'] && ($m['latest_check']['status'] ?? null) === 'up');
        } elseif ($status === 'down') {
            $monitors = $monitors->filter(fn ($m) => $m['is_active'] && ($m['latest_check']['status'] ?? null) === 'down');
        }

        return Inertia::render('Monitors/Index', [
            'monitors' => $monitors->values(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Monitor $monitor): Response
    {
        $checks = $monitor->checks()
            ->latest('checked_at')
            ->limit(50)
            ->get(['id', 'status', 'response_time_ms', 'status_code', 'checked_at']);

        $incidents = $monitor->incidents()
            ->latest('started_at')
            ->limit(20)
            ->get(['id', 'started_at', 'resolved_at', 'cause']);

        $uptimeQuery = fn ($days) => (float) ($monitor->checks()
            ->where('checked_at', '>=', now()->subDays($days))
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
            ->value('uptime') ?? 100);

        return Inertia::render('Monitors/Show', [
            'monitor' => array_merge($monitor->toArray(), [
                'notification_channels' => $monitor->notificationChannels()
                    ->get(['notification_channels.id', 'name', 'type']),
            ]),
            'checks' => $checks,
            'incidents' => $incidents,
            'uptime' => [
                'day' => $uptimeQuery(1),
                'week' => $uptimeQuery(7),
                'month' => $uptimeQuery(30),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Monitors/Create', [
            'notificationChannels' => NotificationChannel::where('is_active', true)
                ->get(['id', 'name', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMonitor($request);

        $channels = $validated['notification_channels'] ?? [];
        unset($validated['notification_channels']);

        $monitor = Monitor::create($validated);
        $monitor->notificationChannels()->sync($channels);

        return to_route('monitors.show', $monitor);
    }

    public function edit(Monitor $monitor): Response
    {
        return Inertia::render('Monitors/Edit', [
            'monitor' => array_merge($monitor->toArray(), [
                'notification_channel_ids' => $monitor->notificationChannels()->pluck('notification_channels.id'),
            ]),
            'notificationChannels' => NotificationChannel::where('is_active', true)
                ->get(['id', 'name', 'type']),
        ]);
    }

    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        $validated = $this->validateMonitor($request);

        $channels = $validated['notification_channels'] ?? [];
        unset($validated['notification_channels']);

        $monitor->update($validated);
        $monitor->notificationChannels()->sync($channels);

        return to_route('monitors.show', $monitor);
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $monitor->delete();

        return to_route('monitors.index');
    }

    public function pause(Monitor $monitor): RedirectResponse
    {
        $monitor->update(['is_active' => false]);

        return back();
    }

    public function resume(Monitor $monitor): RedirectResponse
    {
        $monitor->update(['is_active' => true]);

        return back();
    }

    private function validateMonitor(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'method' => 'required|in:GET,POST,HEAD',
            'expected_status_code' => 'required|integer|min:100|max:599',
            'keyword' => 'nullable|string|max:255',
            'interval' => 'required|integer|min:1|max:60',
            'warning_threshold_ms' => 'nullable|integer|min:1',
            'critical_threshold_ms' => 'nullable|integer|min:1',
            'notification_channels' => 'array',
            'notification_channels.*' => 'exists:notification_channels,id',
        ]);
    }
}
