<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorCheckResource;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MonitorApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = Monitor::query()
            ->with(['checks' => fn ($q) => $q->latest('checked_at')->limit(1)])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return MonitorResource::collection($monitors);
    }

    public function show(Monitor $monitor): MonitorResource
    {
        $monitor->load([
            'checks' => fn ($q) => $q->latest('checked_at')->limit(1),
            'notificationChannels',
        ]);

        $checks = $monitor->checks()->latest('checked_at')->limit(50)->get();
        $incidents = $monitor->incidents()->latest('started_at')->limit(20)->get();

        $uptimeQuery = fn (int $days) => (float) ($monitor->checks()
            ->where('checked_at', '>=', now()->subDays($days))
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'up' THEN 100 ELSE 0 END), 2) as uptime")
            ->value('uptime') ?? 100);

        return (new MonitorResource($monitor))->additional([
            'checks' => MonitorCheckResource::collection($checks),
            'incidents' => $incidents,
            'uptime' => [
                '24h' => $uptimeQuery(1),
                '7d' => $uptimeQuery(7),
                '30d' => $uptimeQuery(30),
                '90d' => $uptimeQuery(90),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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

        $channels = $validated['notification_channels'] ?? [];
        unset($validated['notification_channels']);

        $monitor = Monitor::create(array_merge($validated, [
            'team_id' => $request->user()->team_id,
        ]));

        $monitor->notificationChannels()->sync($channels);

        return (new MonitorResource($monitor))->response()->setStatusCode(201);
    }

    public function update(Request $request, Monitor $monitor): MonitorResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|url|max:2048',
            'method' => 'sometimes|required|in:GET,POST,HEAD',
            'expected_status_code' => 'sometimes|required|integer|min:100|max:599',
            'keyword' => 'nullable|string|max:255',
            'interval' => 'sometimes|required|integer|min:1|max:60',
            'warning_threshold_ms' => 'nullable|integer|min:1',
            'critical_threshold_ms' => 'nullable|integer|min:1',
            'notification_channels' => 'array',
            'notification_channels.*' => 'exists:notification_channels,id',
        ]);

        $channels = $validated['notification_channels'] ?? null;
        unset($validated['notification_channels']);

        $monitor->update($validated);

        if ($channels !== null) {
            $monitor->notificationChannels()->sync($channels);
        }

        return new MonitorResource($monitor);
    }

    public function destroy(Monitor $monitor): Response
    {
        $monitor->delete();

        return response()->noContent();
    }

    public function pause(Monitor $monitor): JsonResponse
    {
        $monitor->update(['is_active' => false]);

        return response()->json(['message' => 'Monitor paused.']);
    }

    public function resume(Monitor $monitor): JsonResponse
    {
        $monitor->update(['is_active' => true]);

        return response()->json(['message' => 'Monitor resumed.']);
    }

    public function checks(Request $request, Monitor $monitor): AnonymousResourceCollection
    {
        $query = $monitor->checks()
            ->when($request->filled('from'), fn ($q) => $q->where('checked_at', '>=', Carbon::parse($request->from)))
            ->when($request->filled('to'), fn ($q) => $q->where('checked_at', '<=', Carbon::parse($request->to)->endOfDay()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('checked_at');

        return MonitorCheckResource::collection($query->paginate($request->integer('per_page', 50)));
    }
}
