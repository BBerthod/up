<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\IncidentCause;
use App\Jobs\SendNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
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
        $monitor = new Monitor([
            'name' => 'Test Monitor',
            'url' => 'https://example.com',
        ]);
        $monitor->id = 0;

        $incident = new MonitorIncident([
            'monitor_id' => 0,
            'cause' => IncidentCause::TIMEOUT,
            'started_at' => now(),
        ]);
        $incident->id = 0;

        $check = new MonitorCheck([
            'monitor_id' => 0,
            'status_code' => 0,
            'response_time_ms' => 0,
            'checked_at' => now(),
        ]);
        $check->id = 0;

        try {
            (new SendNotification($channel, 'down', $monitor, $incident, $check))->handle();

            return back()->with('success', "Test notification sent to {$channel->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', "Test notification failed: {$e->getMessage()}");
        }
    }

    private function validateChannel(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(ChannelType::cases(), 'value'))],
            'settings' => ['present', 'array'],
            'is_active' => ['boolean'],
        ];

        $rules = array_merge($rules, match ($request->input('type')) {
            'email' => ['settings.recipients' => ['required', 'string', 'max:1000']],
            'webhook' => ['settings.url' => ['required', 'url', 'max:2000']],
            'slack' => ['settings.webhook_url' => ['required', 'url', 'max:2000']],
            'discord' => ['settings.webhook_url' => ['required', 'url', 'max:2000']],
            'telegram' => [
                'settings.bot_token' => ['required', 'string', 'max:255'],
                'settings.chat_id' => ['required', 'string', 'max:255'],
            ],
            default => [],
        });

        return $request->validate($rules);
    }
}
