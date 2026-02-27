<?php

namespace App\Http\Controllers\Api;

use App\Enums\IngestEventLevel;
use App\Enums\IngestEventType;
use App\Events\IngestEventReceived;
use App\Http\Controllers\Controller;
use App\Jobs\SendIngestNotification;
use App\Models\IngestEvent;
use App\Models\IngestSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class IngestController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        $source = IngestSource::withoutGlobalScopes()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $source) {
            return response()->json(['error' => 'Invalid or inactive source token.'], 401);
        }

        // Batch or single event
        if ($request->has('events')) {
            return $this->storeBatch($request, $source);
        }

        return $this->storeSingle($request, $source);
    }

    private function storeSingle(Request $request, IngestSource $source): JsonResponse
    {
        $validated = $this->validateEvent($request);

        $event = $this->createEvent($source, $validated);

        $this->broadcastAndNotify($source, $event);

        return response()->json(['id' => $event->id], 201);
    }

    private function storeBatch(Request $request, IngestSource $source): JsonResponse
    {
        $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        $created = [];

        foreach ($request->input('events') as $eventData) {
            $validated = $this->validateEventArray($eventData);
            $event = $this->createEvent($source, $validated);
            $this->broadcastAndNotify($source, $event);
            $created[] = $event->id;
        }

        return response()->json(['ids' => $created, 'count' => count($created)], 201);
    }

    private function createEvent(IngestSource $source, array $data): IngestEvent
    {
        return IngestEvent::create([
            'source_id' => $source->id,
            'type' => $data['type'],
            'level' => $data['level'],
            'message' => $data['message'],
            'context' => $data['context'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'created_at' => now(),
        ]);
    }

    private function broadcastAndNotify(IngestSource $source, IngestEvent $event): void
    {
        IngestEventReceived::dispatch($source, $event);

        if (! $event->shouldNotify()) {
            return;
        }

        $cooldownKey = "ingest_notify:{$source->id}:{$event->level->value}";
        $cooldownMinutes = $event->level->notificationCooldownMinutes();

        if (Cache::has($cooldownKey)) {
            return;
        }

        $channels = $source->notificationChannels()->where('is_active', true)->get();

        foreach ($channels as $channel) {
            SendIngestNotification::dispatch($channel, $source, $event);
        }

        Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate($this->eventRules());
    }

    private function validateEventArray(array $data): array
    {
        return validator($data, $this->eventRules())->validate();
    }

    private function eventRules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(IngestEventType::cases(), 'value'))],
            'level' => ['required', Rule::in(array_column(IngestEventLevel::cases(), 'value'))],
            'message' => ['required', 'string', 'max:10000'],
            'context' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
