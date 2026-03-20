<?php

namespace App\Http\Controllers;

use App\Enums\WarmSiteMode;
use App\Http\Requests\StoreWarmSiteRequest;
use App\Http\Requests\UpdateWarmSiteRequest;
use App\Jobs\RunWarmSite;
use App\Models\WarmSite;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WarmSiteController extends Controller
{
    private function frequencies(): array
    {
        return [
            ['value' => 15, 'label' => '15 minutes'],
            ['value' => 30, 'label' => '30 minutes'],
            ['value' => 60, 'label' => '1 hour'],
            ['value' => 120, 'label' => '2 hours'],
            ['value' => 360, 'label' => '6 hours'],
            ['value' => 720, 'label' => '12 hours'],
            ['value' => 1440, 'label' => '24 hours'],
        ];
    }

    private function modes(): array
    {
        return collect(WarmSiteMode::cases())
            ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
            ->all();
    }

    public function index(): Response
    {
        $warmSites = WarmSite::with('latestRun')
            ->orderBy('name')
            ->get()
            ->map(fn ($site) => [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->domain,
                'mode' => $site->mode->value,
                'mode_label' => $site->mode->label(),
                'frequency_minutes' => $site->frequency_minutes,
                'is_active' => $site->is_active,
                'last_warmed_at' => $site->last_warmed_at?->toIso8601String(),
                'last_run' => $site->latestRun ? [
                    'urls_total' => $site->latestRun->urls_total,
                    'urls_hit' => $site->latestRun->urls_hit,
                    'urls_miss' => $site->latestRun->urls_miss,
                    'urls_error' => $site->latestRun->urls_error,
                    'hit_ratio' => $site->latestRun->hit_ratio,
                    'avg_response_ms' => $site->latestRun->avg_response_ms,
                    'status' => $site->latestRun->status->value,
                ] : null,
            ]);

        return Inertia::render('CacheWarming/Index', [
            'warmSites' => $warmSites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CacheWarming/Create', [
            'frequencies' => $this->frequencies(),
            'modes' => $this->modes(),
        ]);
    }

    public function store(StoreWarmSiteRequest $request): RedirectResponse
    {
        $warming = WarmSite::create($request->validated());

        return to_route('warming.show', $warming);
    }

    public function show(WarmSite $warming): Response
    {
        $this->authorize('view', $warming);

        $recentRuns = $warming->warmRuns()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($run) => [
                'id' => $run->id,
                'urls_total' => $run->urls_total,
                'urls_hit' => $run->urls_hit,
                'urls_miss' => $run->urls_miss,
                'urls_error' => $run->urls_error,
                'hit_ratio' => $run->hit_ratio,
                'avg_response_ms' => $run->avg_response_ms,
                'status' => $run->status->value,
                'error_message' => $run->error_message,
                'duration_seconds' => $run->duration_seconds,
                'started_at' => $run->started_at->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
            ]);

        return Inertia::render('CacheWarming/Show', [
            'warmSite' => [
                'id' => $warming->id,
                'name' => $warming->name,
                'domain' => $warming->domain,
                'mode' => $warming->mode->value,
                'mode_label' => $warming->mode->label(),
                'sitemap_url' => $warming->sitemap_url,
                'urls' => $warming->urls,
                'frequency_minutes' => $warming->frequency_minutes,
                'max_urls' => $warming->max_urls,
                'is_active' => $warming->is_active,
                'last_warmed_at' => $warming->last_warmed_at?->toIso8601String(),
            ],
            'recentRuns' => $recentRuns,
        ]);
    }

    public function edit(WarmSite $warming): Response
    {
        $this->authorize('update', $warming);

        return Inertia::render('CacheWarming/Edit', [
            'warmSite' => [
                'id' => $warming->id,
                'name' => $warming->name,
                'domain' => $warming->domain,
                'mode' => $warming->mode->value,
                'sitemap_url' => $warming->sitemap_url,
                'urls' => $warming->urls,
                'frequency_minutes' => $warming->frequency_minutes,
                'max_urls' => $warming->max_urls,
                'is_active' => $warming->is_active,
            ],
            'frequencies' => $this->frequencies(),
            'modes' => $this->modes(),
        ]);
    }

    public function update(UpdateWarmSiteRequest $request, WarmSite $warming): RedirectResponse
    {
        $this->authorize('update', $warming);

        $warming->update($request->validated());

        return to_route('warming.show', $warming);
    }

    public function destroy(WarmSite $warming): RedirectResponse
    {
        $this->authorize('delete', $warming);

        $warming->delete();

        return to_route('warming.index');
    }

    public function warmNow(WarmSite $warming): RedirectResponse
    {
        $this->authorize('warmNow', $warming);

        RunWarmSite::dispatch($warming);

        return back()->with('success', 'Cache warming queued successfully.');
    }
}
