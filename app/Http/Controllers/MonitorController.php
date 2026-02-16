<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Jobs\RunLighthouseAudit;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function show(Request $request, Monitor $monitor): Response
    {
        $period = $request->query('period', '24h');
        if (! in_array($period, ['6mo', '3mo', '1mo', '7d', '24h', '1h'])) {
            $period = '24h';
        }

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

        $heatmapData = $monitor->checks()
            ->where('checked_at', '>=', now()->subYear())
            ->selectRaw('DATE(checked_at) as date, ROUND(AVG(response_time_ms)) as avg_ms')
            ->groupByRaw('DATE(checked_at)')
            ->orderBy('date')
            ->pluck('avg_ms', 'date')
            ->map(fn ($v) => (int) $v);

        $lighthouseScore = $monitor->lighthouseScores()
            ->latest('scored_at')
            ->first(['performance', 'accessibility', 'best_practices', 'seo', 'lcp', 'fcp', 'cls', 'tbt', 'speed_index', 'scored_at']);

        $badgeHash = base64_encode(pack('V', $monitor->id));

        $chartData = $this->getChartData($monitor, $period);

        return Inertia::render('Monitors/Show', [
            'monitor' => array_merge($monitor->toArray(), [
                'notification_channels' => $monitor->notificationChannels()
                    ->get(['notification_channels.id', 'name', 'type']),
                'badge_hash' => $badgeHash,
            ]),
            'checks' => $checks,
            'incidents' => $incidents,
            'uptime' => [
                'day' => $uptimeQuery(1),
                'week' => $uptimeQuery(7),
                'month' => $uptimeQuery(30),
            ],
            'heatmapData' => $heatmapData,
            'lighthouseScore' => $lighthouseScore,
            'lighthouseHistory' => $this->getLighthouseHistory($request, $monitor),
            'chartData' => $chartData,
            'currentPeriod' => $period,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Monitors/Create', [
            'notificationChannels' => NotificationChannel::where('is_active', true)
                ->get(['id', 'name', 'type']),
        ]);
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function update(UpdateMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $validated = $request->validated();

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

    public function lighthouse(Monitor $monitor): RedirectResponse
    {
        if ($monitor->type->value !== 'http') {
            abort(422, 'Lighthouse audits are only available for HTTP monitors.');
        }

        $cacheKey = "lighthouse_audit:{$monitor->id}";
        if (Cache::has($cacheKey)) {
            return back()->with('error', 'A Lighthouse audit was recently triggered. Please wait 5 minutes.');
        }

        Cache::put($cacheKey, true, now()->addMinutes(5));
        RunLighthouseAudit::dispatch($monitor);

        return back()->with('success', 'Lighthouse audit queued successfully.');
    }

    public function lighthouseHistory(Request $request, Monitor $monitor): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->getLighthouseHistory($request, $monitor));
    }

    private function getLighthouseHistory(Request $request, Monitor $monitor): array
    {
        $period = $request->query('lh_period', '30d');
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        return $monitor->lighthouseScores()
            ->where('scored_at', '>=', now()->subDays($days))
            ->orderBy('scored_at')
            ->get(['performance', 'accessibility', 'best_practices', 'seo', 'lcp', 'fcp', 'cls', 'tbt', 'speed_index', 'scored_at'])
            ->toArray();
    }

    private function getChartData(Monitor $monitor, string $period): array
    {
        if (in_array($period, ['6mo', '3mo', '1mo'])) {
            $from = match ($period) {
                '6mo' => now()->subMonths(6),
                '3mo' => now()->subMonths(3),
                '1mo' => now()->subMonth(),
            };

            return $this->getAggregatedChartData($monitor, $from);
        }

        [$from, $limit] = match ($period) {
            '7d' => [now()->subDays(7), 2000],
            '24h' => [now()->subDay(), 1500],
            '1h' => [now()->subHour(), 500],
        };

        return $this->getRawChartData($monitor, $from, $limit);
    }

    private function getAggregatedChartData(Monitor $monitor, Carbon $from): array
    {
        return $monitor->checks()
            ->selectRaw("
                DATE(checked_at) as date,
                ROUND(AVG(response_time_ms)) as avg_ms,
                MIN(response_time_ms) as min_ms,
                MAX(response_time_ms) as max_ms,
                ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime_percent
            ")
            ->where('checked_at', '>=', $from)
            ->groupByRaw('DATE(checked_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getRawChartData(Monitor $monitor, Carbon $from, int $limit): array
    {
        return $monitor->checks()
            ->select(['id', 'status', 'response_time_ms', 'status_code', 'checked_at'])
            ->where('checked_at', '>=', $from)
            ->oldest('checked_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
