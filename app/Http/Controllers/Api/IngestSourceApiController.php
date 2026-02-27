<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IngestSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class IngestSourceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sources = IngestSource::query()
            ->withCount('events')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($sources);
    }

    public function show(IngestSource $ingestSource): JsonResponse
    {
        $this->authorize('view', $ingestSource);

        return response()->json($ingestSource->load('notificationChannels'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'notification_channel_ids' => ['nullable', 'array'],
            'notification_channel_ids.*' => ['integer', 'exists:notification_channels,id'],
        ]);

        $source = IngestSource::create([
            'name' => $validated['name'],
            'slug' => IngestSource::generateSlug($validated['name']),
            'token' => IngestSource::generateToken(),
            'is_active' => $validated['is_active'] ?? true,
            'team_id' => $request->user()->team_id,
        ]);

        if (! empty($validated['notification_channel_ids'])) {
            $source->notificationChannels()->sync($validated['notification_channel_ids']);
        }

        return response()->json($source, 201);
    }

    public function update(Request $request, IngestSource $ingestSource): JsonResponse
    {
        $this->authorize('update', $ingestSource);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'notification_channel_ids' => ['nullable', 'array'],
            'notification_channel_ids.*' => ['integer', 'exists:notification_channels,id'],
        ]);

        if (isset($validated['name'])) {
            $ingestSource->slug = IngestSource::generateSlug($validated['name']);
        }

        $ingestSource->update(array_filter($validated, fn ($k) => ! in_array($k, ['notification_channel_ids']), ARRAY_FILTER_USE_KEY));

        if (array_key_exists('notification_channel_ids', $validated)) {
            $ingestSource->notificationChannels()->sync($validated['notification_channel_ids'] ?? []);
        }

        return response()->json($ingestSource);
    }

    public function destroy(IngestSource $ingestSource): Response
    {
        $this->authorize('delete', $ingestSource);

        $ingestSource->delete();

        return response()->noContent();
    }

    public function rotateToken(IngestSource $ingestSource): JsonResponse
    {
        $this->authorize('update', $ingestSource);

        $ingestSource->update(['token' => IngestSource::generateToken()]);

        return response()->json(['token' => $ingestSource->token]);
    }

    public function events(Request $request, IngestSource $ingestSource): JsonResponse
    {
        $this->authorize('view', $ingestSource);

        $query = $ingestSource->events()
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->input('level')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('occurred_at');

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }
}
