<?php

namespace App\Http\Controllers;

use App\Enums\IncidentCause;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class IncidentController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = MonitorIncident::query()
            ->join('monitors', 'monitor_incidents.monitor_id', '=', 'monitors.id')
            ->select([
                'monitor_incidents.*',
                'monitors.name as monitor_name',
                'monitors.type as monitor_type',
                'monitors.url as monitor_url',
            ]);

        // Filters
        if ($request->filled('status')) {
            match ($request->input('status')) {
                'active' => $query->whereNull('monitor_incidents.resolved_at'),
                'resolved' => $query->whereNotNull('monitor_incidents.resolved_at'),
                default => null,
            };
        }

        if ($request->filled('cause')) {
            $query->where('monitor_incidents.cause', $request->input('cause'));
        }

        if ($request->filled('monitor_id')) {
            $query->where('monitor_incidents.monitor_id', $request->input('monitor_id'));
        }

        if ($request->filled('from')) {
            $query->where('monitor_incidents.started_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('monitor_incidents.started_at', '<=', $request->input('to').' 23:59:59');
        }

        // Sorting
        $sortBy = $request->input('sort', 'started_at');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['started_at', 'resolved_at', 'monitor_name'];

        if (in_array($sortBy, $allowedSorts)) {
            $column = $sortBy === 'monitor_name' ? 'monitors.name' : "monitor_incidents.{$sortBy}";
            $query->orderBy($column, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('monitor_incidents.started_at');
        }

        $activeCount = MonitorIncident::whereNull('resolved_at')->count();

        return Inertia::render('Incidents/Index', [
            'incidents' => $query->paginate(25)->withQueryString(),
            'activeCount' => $activeCount,
            'monitors' => Monitor::select('id', 'name')->orderBy('name')->get(),
            'causes' => collect(IncidentCause::cases())->map(fn ($c) => ['value' => $c->value, 'label' => ucfirst(str_replace('_', ' ', $c->value))]),
            'filters' => $request->only(['status', 'cause', 'monitor_id', 'from', 'to', 'sort', 'dir']),
        ]);
    }

    public function export(Request $request): Response
    {
        $format = $request->input('format', 'csv');

        $query = MonitorIncident::query()
            ->join('monitors', 'monitor_incidents.monitor_id', '=', 'monitors.id')
            ->select([
                'monitors.name as monitor_name',
                'monitors.type as monitor_type',
                'monitor_incidents.cause',
                'monitor_incidents.started_at',
                'monitor_incidents.resolved_at',
            ]);

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'active' => $query->whereNull('monitor_incidents.resolved_at'),
                'resolved' => $query->whereNotNull('monitor_incidents.resolved_at'),
                default => null,
            };
        }

        if ($request->filled('cause')) {
            $query->where('monitor_incidents.cause', $request->input('cause'));
        }

        if ($request->filled('monitor_id')) {
            $query->where('monitor_incidents.monitor_id', $request->input('monitor_id'));
        }

        if ($request->filled('from')) {
            $query->where('monitor_incidents.started_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('monitor_incidents.started_at', '<=', $request->input('to').' 23:59:59');
        }

        $incidents = $query->orderByDesc('monitor_incidents.started_at')->limit(10000)->get();

        if ($format === 'json') {
            $data = $incidents->map(fn ($i) => [
                'monitor' => $i->monitor_name,
                'type' => $i->monitor_type,
                'status' => $i->resolved_at ? 'resolved' : 'active',
                'cause' => $i->cause,
                'started_at' => $i->started_at,
                'resolved_at' => $i->resolved_at,
                'duration_seconds' => $i->resolved_at
                    ? $i->started_at->diffInSeconds($i->resolved_at)
                    : null,
            ]);

            return response($data->toJson(JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="incidents-'.now()->format('Y-m-d').'.json"',
            ]);
        }

        // CSV
        $csv = "Monitor,Type,Status,Cause,Started,Resolved,Duration (seconds)\n";
        foreach ($incidents as $i) {
            $status = $i->resolved_at ? 'resolved' : 'active';
            $duration = $i->resolved_at
                ? $i->started_at->diffInSeconds($i->resolved_at)
                : '';
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                '"'.str_replace('"', '""', $i->monitor_name).'"',
                $i->monitor_type,
                $status,
                $i->cause,
                $i->started_at,
                $i->resolved_at ?? '',
                $duration,
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="incidents-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
