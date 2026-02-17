<?php

namespace App\Http\Controllers;

use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Models\StatusPage;
use Inertia\Inertia;
use Inertia\Response;

class PublicStatusPageController extends Controller
{
    public function show(string $slug): Response
    {
        $statusPage = StatusPage::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $statusPage->load(['monitors' => fn ($q) => $q->orderByPivot('sort_order')]);

        $monitorIds = $statusPage->monitors->pluck('id');

        $latestChecks = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->whereIn('id', fn ($q) => $q->selectRaw('MAX(id)')->from('monitor_checks')->whereIn('monitor_id', $monitorIds)->groupBy('monitor_id'))
            ->get()
            ->keyBy('monitor_id');

        $dailyStats = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subDays(90))
            ->selectRaw('monitor_id, DATE(checked_at) as date')
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
            ->groupBy('monitor_id', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('monitor_id');

        $uptime90d = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subDays(90))
            ->selectRaw('monitor_id')
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 2) as uptime")
            ->groupBy('monitor_id')
            ->pluck('uptime', 'monitor_id');

        $monitorsData = $statusPage->monitors->map(function ($monitor) use ($latestChecks, $dailyStats, $uptime90d) {
            $monitorDailyStats = $dailyStats->get($monitor->id, collect())->keyBy('date');
            $latestCheck = $latestChecks->get($monitor->id);

            $breakdown = [];
            for ($i = 89; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $uptime = $monitorDailyStats->get($date)?->uptime;
                $breakdown[] = [
                    'date' => $date,
                    'status' => $uptime === null ? 'no-data' : ($uptime >= 99 ? 'up' : ($uptime >= 50 ? 'partial' : 'down')),
                ];
            }

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'current_status' => $latestCheck?->status->value ?? 'unknown',
                'response_time_ms' => $latestCheck?->response_time_ms,
                'uptime_90d' => (float) ($uptime90d->get($monitor->id) ?? 0),
                'daily_breakdown' => $breakdown,
            ];
        });

        $activeIncidents = MonitorIncident::whereIn('monitor_id', $monitorIds)
            ->whereNull('resolved_at')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'monitor_name' => $statusPage->monitors->firstWhere('id', $i->monitor_id)?->name,
                'cause' => $i->cause->value,
                'started_at' => $i->started_at->toIso8601String(),
            ]);

        return Inertia::render('StatusPages/Public', [
            'statusPage' => [
                'name' => $statusPage->name,
                'description' => $statusPage->description,
                'theme' => $statusPage->theme,
            ],
            'monitors' => $monitorsData,
            'activeIncidents' => $activeIncidents,
        ]);
    }
}
