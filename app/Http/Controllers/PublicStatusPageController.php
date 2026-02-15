<?php

namespace App\Http\Controllers;

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

        // Get daily uptime breakdown for last 90 days (single query per monitor)
        $monitorsData = $statusPage->monitors->map(function ($monitor) {
            $latestCheck = $monitor->checks()->latest('checked_at')->first();

            // Aggregate daily uptime in a single query
            $dailyStats = $monitor->checks()
                ->where('checked_at', '>=', now()->subDays(90))
                ->selectRaw('DATE(checked_at) as date')
                ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 1) as uptime")
                ->groupByRaw('DATE(checked_at)')
                ->orderBy('date')
                ->pluck('uptime', 'date');

            // Build 90-day breakdown
            $breakdown = [];
            for ($i = 89; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $uptime = $dailyStats[$date] ?? null;
                $breakdown[] = [
                    'date' => $date,
                    'status' => $uptime === null ? 'no-data' : ($uptime >= 99 ? 'up' : ($uptime >= 50 ? 'partial' : 'down')),
                ];
            }

            // 90-day overall uptime
            $uptime90d = (float) ($monitor->checks()
                ->where('checked_at', '>=', now()->subDays(90))
                ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 2) as uptime")
                ->value('uptime') ?? 0);

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'current_status' => $latestCheck?->status->value ?? 'unknown',
                'response_time_ms' => $latestCheck?->response_time_ms,
                'uptime_90d' => $uptime90d,
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
