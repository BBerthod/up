<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Jobs\RunLighthouseAudit;
use App\Models\Monitor;
use App\Models\MonitorCheck;
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
            ->withCount(['incidents as active_incidents_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->addSelect([
                'uptime_24h' => MonitorCheck::selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1)")
                    ->whereColumn('monitor_checks.monitor_id', 'monitors.id')
                    ->where('checked_at', '>=', now()->subDay()),
            ]);

        if ($status === 'paused') {
            $query->inactive();
        }

        $monitors = $query->orderByRaw('uptime_24h ASC NULLS LAST')->get()->map(function ($monitor) {
            $latestCheck = $monitor->checks->first();

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
                'uptime_24h' => $monitor->uptime_24h !== null ? (float) $monitor->uptime_24h : null,
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
        $this->authorize('view', $monitor);

        $period = $request->query('period', '24h');
        if (! in_array($period, ['6mo', '3mo', '1mo', '7d', '24h', '1h'])) {
            $period = '24h';
        }

        $checks = $monitor->checks()
            ->latest('checked_at')
            ->limit(50)
            ->get(['id', 'status', 'response_time_ms', 'status_code', 'checked_at']);

        $incidentSort = in_array($request->query('incident_sort'), ['started_at', 'resolved_at', 'cause'])
            ? $request->query('incident_sort')
            : 'started_at';
        $incidentDir = $request->query('incident_dir') === 'asc' ? 'asc' : 'desc';

        $incidents = $monitor->incidents()
            ->orderBy($incidentSort, $incidentDir)
            ->paginate(15, ['id', 'started_at', 'resolved_at', 'cause'], 'incident_page');

        $incidentStats = Cache::remember("monitor:{$monitor->id}:incident_stats", 300, function () use ($monitor) {
            $activeIncident = $monitor->incidents()
                ->whereNull('resolved_at')
                ->latest('started_at')
                ->first(['id', 'started_at', 'cause']);

            return [
                'total' => $monitor->incidents()->count(),
                'active' => $monitor->incidents()->whereNull('resolved_at')->count(),
                'active_incident' => $activeIncident ? [
                    'id' => $activeIncident->id,
                    'started_at' => $activeIncident->started_at->toIso8601String(),
                    'cause' => $activeIncident->cause->value,
                ] : null,
                'mttr_minutes' => (int) round((float) ($monitor->incidents()
                    ->whereNotNull('resolved_at')
                    ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - started_at)) / 60) as mttr')
                    ->value('mttr') ?? 0)),
                'downtime_30d_minutes' => (int) round((float) ($monitor->incidents()
                    ->where('started_at', '>=', now()->subDays(30))
                    ->selectRaw('SUM(EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at)) / 60) as total')
                    ->value('total') ?? 0)),
            ];
        });

        $incidentTimeline = $monitor->incidents()
            ->where('started_at', '>=', now()->subDays(90))
            ->orderBy('started_at')
            ->get(['id', 'started_at', 'resolved_at', 'cause'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'started_at' => $i->started_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
                'cause' => $i->cause->value,
            ]);

        $uptimeData = Cache::remember("monitor:{$monitor->id}:uptime", 300, function () use ($monitor) {
            $calc = fn ($days) => (float) ($monitor->checks()
                ->where('checked_at', '>=', now()->subDays($days))
                ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
                ->value('uptime') ?? 100);

            return [
                'day' => $calc(1),
                'week' => $calc(7),
                'month' => $calc(30),
            ];
        });

        $heatmapData = Cache::remember("monitor:{$monitor->id}:heatmap", 300, function () use ($monitor) {
            return $monitor->checks()
                ->where('checked_at', '>=', now()->subYear())
                ->selectRaw('DATE(checked_at) as date, ROUND(AVG(response_time_ms)) as avg_ms')
                ->groupByRaw('DATE(checked_at)')
                ->orderBy('date')
                ->pluck('avg_ms', 'date')
                ->map(fn ($v) => (int) $v);
        });

        $lighthouseScore = $monitor->lighthouseScores()
            ->latest('scored_at')
            ->first(['performance', 'accessibility', 'best_practices', 'seo', 'lcp', 'fcp', 'cls', 'tbt', 'speed_index', 'scored_at']);

        $badgeHash = rtrim(strtr(base64_encode(pack('V', $monitor->id)), '+/', '-_'), '=');

        $chartData = $this->getChartData($monitor, $period);

        $functionalChecks = $monitor->functionalChecks()
            ->with(['results' => fn ($q) => $q->latest('checked_at')->limit(1)])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($fc) => [
                'id' => $fc->id,
                'name' => $fc->name,
                'url' => $fc->url,
                'resolved_url' => $fc->resolveUrl(),
                'type' => $fc->type->value,
                'rules' => $fc->rules,
                'check_interval' => $fc->check_interval,
                'is_enabled' => $fc->is_enabled,
                'last_status' => $fc->last_status->value,
                'last_checked_at' => $fc->last_checked_at?->toIso8601String(),
                'last_result' => $fc->results->first() ? [
                    'status' => $fc->results->first()->status->value,
                    'duration_ms' => $fc->results->first()->duration_ms,
                    'details' => $fc->results->first()->details,
                    'checked_at' => $fc->results->first()->checked_at->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('Monitors/Show', [
            'monitor' => array_merge($monitor->toArray(), [
                'notification_channels' => $monitor->notificationChannels()
                    ->get(['notification_channels.id', 'name', 'type']),
                'badge_hash' => $badgeHash,
            ]),
            'checks' => $checks,
            'incidents' => $incidents,
            'incidentStats' => $incidentStats,
            'incidentTimeline' => $incidentTimeline,
            'incidentSort' => $incidentSort,
            'incidentDir' => $incidentDir,
            'uptime' => $uptimeData,
            'heatmapData' => $heatmapData,
            'lighthouseScore' => $lighthouseScore,
            'lighthouseHistory' => $this->getLighthouseHistory($request, $monitor),
            'chartData' => $chartData,
            'currentPeriod' => $period,
            'functionalChecks' => $functionalChecks,
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
        $this->authorize('update', $monitor);

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
        $this->authorize('update', $monitor);

        $validated = $request->validated();

        $channels = $validated['notification_channels'] ?? [];
        unset($validated['notification_channels']);

        $monitor->update($validated);
        $monitor->notificationChannels()->sync($channels);

        return to_route('monitors.show', $monitor);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:pause,resume,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $monitors = Monitor::whereIn('id', $validated['ids'])->get();
        foreach ($monitors as $monitor) {
            $this->authorize($validated['action'] === 'delete' ? 'delete' : 'update', $monitor);
        }

        match ($validated['action']) {
            'pause' => Monitor::whereIn('id', $validated['ids'])->update(['is_active' => false]),
            'resume' => Monitor::whereIn('id', $validated['ids'])->update(['is_active' => true]),
            'delete' => Monitor::whereIn('id', $validated['ids'])->delete(),
        };

        return back()->with('success', count($validated['ids']).' monitors updated.');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return to_route('monitors.index');
    }

    public function pause(Monitor $monitor): RedirectResponse
    {
        $this->authorize('pause', $monitor);

        $monitor->update(['is_active' => false]);

        return back();
    }

    public function resume(Monitor $monitor): RedirectResponse
    {
        $this->authorize('resume', $monitor);

        $monitor->update(['is_active' => true]);

        return back();
    }

    public function lighthouse(Monitor $monitor): RedirectResponse
    {
        $this->authorize('lighthouse', $monitor);

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

    public function purge(Request $request, Monitor $monitor): RedirectResponse
    {
        $this->authorize('purge', $monitor);

        $validated = $request->validate([
            'target' => ['required', 'string', 'in:checks,incidents,lighthouse'],
            'period' => ['required', 'string', 'in:all,30d,90d,1y'],
        ]);

        $before = match ($validated['period']) {
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => null,
        };

        match ($validated['target']) {
            'checks' => $before
                ? $monitor->checks()->where('checked_at', '<', $before)->delete()
                : $monitor->checks()->delete(),
            'incidents' => $before
                ? $monitor->incidents()->where('started_at', '<', $before)->delete()
                : $monitor->incidents()->delete(),
            'lighthouse' => $before
                ? $monitor->lighthouseScores()->where('scored_at', '<', $before)->delete()
                : $monitor->lighthouseScores()->delete(),
        };

        Cache::forget("monitor:{$monitor->id}:uptime");
        Cache::forget("monitor:{$monitor->id}:heatmap");
        Cache::forget("monitor:{$monitor->id}:incident_stats");

        return back()->with('success', 'Monitor data purged successfully.');
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
