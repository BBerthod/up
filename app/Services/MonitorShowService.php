<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitorShowService
{
    public function buildPayload(Monitor $monitor, Request $request): array
    {
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

        $incidentStats = $this->buildIncidentStats($monitor);

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
                ->uptimePercent(1)
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

        $badgeSecret = $monitor->badge_secret;

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

        return [
            'monitor' => array_merge($monitor->toArray(), [
                'notification_channels' => $monitor->notificationChannels()
                    ->get(['notification_channels.id', 'name', 'type']),
                'badge_secret' => $badgeSecret,
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
        ];
    }

    private function buildIncidentStats(Monitor $monitor): array
    {
        return Cache::remember("monitor:{$monitor->id}:incident_stats", 300, function () use ($monitor) {
            $stats = $monitor->incidents()
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN resolved_at IS NULL THEN 1 END) as active,
                    AVG(EXTRACT(EPOCH FROM (resolved_at - started_at))/60) FILTER (WHERE resolved_at IS NOT NULL) as mttr_minutes,
                    COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at))/60) FILTER (WHERE started_at >= NOW() - interval '30 days'), 0) as downtime_30d_minutes
                ")
                ->first();

            $activeIncident = $monitor->incidents()
                ->whereNull('resolved_at')
                ->latest('started_at')
                ->first(['id', 'started_at', 'cause']);

            return [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active ?? 0),
                'active_incident' => $activeIncident ? [
                    'id' => $activeIncident->id,
                    'started_at' => $activeIncident->started_at->toIso8601String(),
                    'cause' => $activeIncident->cause->value,
                ] : null,
                'mttr_minutes' => (int) round((float) ($stats->mttr_minutes ?? 0)),
                'downtime_30d_minutes' => (int) round((float) ($stats->downtime_30d_minutes ?? 0)),
            ];
        });
    }

    public function getLighthouseHistory(Request $request, Monitor $monitor): array
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
            ->selectRaw('
                DATE(checked_at) as date,
                ROUND(AVG(response_time_ms)) as avg_ms,
                MIN(response_time_ms) as min_ms,
                MAX(response_time_ms) as max_ms,
                '.MonitorCheck::uptimeRaw(1).' as uptime_percent
            ')
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
