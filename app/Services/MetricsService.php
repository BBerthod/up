<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
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
        ];
    }
}
