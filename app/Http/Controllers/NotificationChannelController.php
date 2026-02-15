<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Models\NotificationChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationChannelController extends Controller
{
    public function index(): Response
    {
        $channels = NotificationChannel::query()
            ->latest()
            ->get()
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'type' => $channel->type->value,
                'is_active' => $channel->is_active,
                'settings' => $channel->settings,
            ]);

        return Inertia::render('NotificationChannels/Index', [
            'channels' => $channels,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('NotificationChannels/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateChannel($request);

        NotificationChannel::create(array_merge($validated, [
            'team_id' => $request->user()->team_id,
        ]));

        return to_route('channels.index')->with('success', 'Notification channel created.');
    }

    public function edit(NotificationChannel $channel): Response
    {
        return Inertia::render('NotificationChannels/Edit', [
            'channel' => [
                'id' => $channel->id,
                'name' => $channel->name,
                'type' => $channel->type->value,
                'is_active' => $channel->is_active,
                'settings' => $channel->settings,
            ],
        ]);
    }

    public function update(Request $request, NotificationChannel $channel): RedirectResponse
    {
        $validated = $this->validateChannel($request);

        $channel->update($validated);

        return to_route('channels.index')->with('success', 'Notification channel updated.');
    }

    public function destroy(NotificationChannel $channel): RedirectResponse
    {
        $channel->delete();

        return to_route('channels.index')->with('success', 'Notification channel deleted.');
    }

    public function test(NotificationChannel $channel): RedirectResponse
    {
        // TODO: Implement actual test notification per channel type
        return back()->with('success', "Test notification sent to {$channel->name}.");
    }

    private function validateChannel(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(ChannelType::cases(), 'value'))],
            'settings' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);
    }
}
