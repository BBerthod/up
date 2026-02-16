<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\MonitorLighthouseScore;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    public function getDashboardMetrics(): array
    {
        $totalMonitors = Monitor::count();
        $monitorsPaused = Monitor::inactive()->count();

        $latestChecks = MonitorCheck::query()
            ->select('monitor_id', 'status')
            ->whereIn('id', function ($q) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('monitor_checks')
                    ->groupBy('monitor_id');
            })
            ->get();

        $activeMonitorIds = Monitor::active()->pluck('id');
        $monitorsUp = $latestChecks->filter(fn ($c) => $activeMonitorIds->contains($c->monitor_id) && $c->status->value === 'up')->count();
        $monitorsDown = $latestChecks->filter(fn ($c) => $activeMonitorIds->contains($c->monitor_id) && $c->status->value === 'down')->count();

        $checksLast24h = MonitorCheck::where('checked_at', '>=', now()->subDay());

        $avgUptime24h = (float) ($checksLast24h->clone()
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
            ->value('uptime') ?? 100);

        $avgResponseTime24h = (int) ($checksLast24h->clone()->avg('response_time_ms') ?? 0);

        $totalChecksToday = MonitorCheck::where('checked_at', '>=', now()->startOfDay())->count();

        $activeIncidents = MonitorIncident::whereNull('resolved_at')->count();

        $downMonitorIds = $latestChecks
            ->filter(fn ($c) => $activeMonitorIds->contains($c->monitor_id) && $c->status->value === 'down')
            ->pluck('monitor_id');

        $downMonitors = Monitor::whereIn('id', $downMonitorIds)
            ->limit(5)
            ->get(['id', 'name', 'url', 'last_checked_at'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'url' => $m->url,
                'last_checked_at' => $m->last_checked_at?->toIso8601String(),
            ]);

        $recentIncidents = MonitorIncident::with('monitor:id,name')
            ->orderByDesc('started_at')
            ->limit(5)
            ->get(['id', 'monitor_id', 'cause', 'started_at', 'resolved_at'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'monitor_name' => $i->monitor->name,
                'monitor_id' => $i->monitor_id,
                'cause' => $i->cause->value,
                'started_at' => $i->started_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
            ]);

        $responseTimeChart = MonitorCheck::where('checked_at', '>=', now()->subDay())
            ->selectRaw("DATE_TRUNC('hour', checked_at) as hour, ROUND(AVG(response_time_ms)) as avg_ms")
            ->groupByRaw("DATE_TRUNC('hour', checked_at)")
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'hour' => $r->hour,
                'avg_ms' => (int) $r->avg_ms,
            ]);

        $monitorsOverview = Monitor::active()
            ->get(['id', 'name', 'type', 'last_checked_at'])
            ->map(function ($m) use ($latestChecks) {
                $latestCheck = $latestChecks->firstWhere('monitor_id', $m->id);
                $status = $latestCheck ? $latestCheck->status->value : 'unknown';

                $uptime24h = (float) (MonitorCheck::where('monitor_id', $m->id)
                    ->where('checked_at', '>=', now()->subDay())
                    ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
                    ->value('uptime') ?? 100);

                $uptime7d = (float) (MonitorCheck::where('monitor_id', $m->id)
                    ->where('checked_at', '>=', now()->subDays(7))
                    ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
                    ->value('uptime') ?? 100);

                $lastResponseMs = MonitorCheck::where('monitor_id', $m->id)
                    ->orderByDesc('checked_at')
                    ->value('response_time_ms') ?? 0;

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'type' => $m->type->value,
                    'status' => $status,
                    'uptime_24h' => $uptime24h,
                    'uptime_7d' => $uptime7d,
                    'last_response_ms' => (int) $lastResponseMs,
                ];
            });

        $lighthouseOverview = MonitorLighthouseScore::query()
            ->whereIn('id', function ($q) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('monitor_lighthouse_scores')
                    ->groupBy('monitor_id');
            })
            ->with('monitor:id,name')
            ->get(['id', 'monitor_id', 'performance', 'accessibility', 'best_practices', 'seo', 'scored_at'])
            ->map(fn ($s) => [
                'monitor_id' => $s->monitor_id,
                'monitor_name' => $s->monitor->name,
                'performance' => $s->performance,
                'accessibility' => $s->accessibility,
                'best_practices' => $s->best_practices,
                'seo' => $s->seo,
                'scored_at' => $s->scored_at->toIso8601String(),
            ]);

        return [
            'total_monitors' => $totalMonitors,
            'monitors_up' => $monitorsUp,
            'monitors_down' => $monitorsDown,
            'monitors_paused' => $monitorsPaused,
            'avg_uptime_24h' => $avgUptime24h,
            'avg_response_time_24h' => $avgResponseTime24h,
            'total_checks_today' => $totalChecksToday,
            'active_incidents' => $activeIncidents,
            'down_monitors' => $downMonitors,
            'recent_incidents' => $recentIncidents,
            'response_time_chart' => $responseTimeChart,
            'monitors_overview' => $monitorsOverview,
            'lighthouse_overview' => $lighthouseOverview,
        ];
    }
}
