<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationChannelResource;
use App\Models\NotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class NotificationChannelApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $channels = NotificationChannel::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return NotificationChannelResource::collection($channels);
    }

    public function show(NotificationChannel $notificationChannel): NotificationChannelResource
    {
        return new NotificationChannelResource($notificationChannel);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(ChannelType::cases(), 'value'))],
            'settings' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);

        $channel = NotificationChannel::create(array_merge($validated, [
            'team_id' => $request->user()->team_id,
        ]));

        return (new NotificationChannelResource($channel))->response()->setStatusCode(201);
    }

    public function update(Request $request, NotificationChannel $notificationChannel): NotificationChannelResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(array_column(ChannelType::cases(), 'value'))],
            'settings' => ['sometimes', 'required', 'array'],
            'is_active' => ['boolean'],
        ]);

        $notificationChannel->update($validated);

        return new NotificationChannelResource($notificationChannel);
    }

    public function destroy(NotificationChannel $notificationChannel): Response
    {
        $notificationChannel->delete();

        return response()->noContent();
    }
}
