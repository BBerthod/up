# Cache Warming v2 Features — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 8 features to the existing cache warming system: custom headers, sitemap index support, hit ratio chart, real-time Reverb progress, pruning job, failure notifications, monitor linking, and per-URL detail.

**Architecture:** Each feature is independent and builds on the existing WarmSite/WarmRun models, WarmingService, RunWarmSite job, and WarmSiteController. New tables for per-URL detail and monitor linking. New event for Reverb broadcasting. Notification integration via existing NotificationService pattern.

**Tech Stack:** Laravel 12, Vue 3 + Inertia v2, PrimeVue, PostgreSQL, Redis, Laravel Reverb

**Existing code reference:** `app/Services/WarmingService.php`, `app/Jobs/RunWarmSite.php`, `app/Http/Controllers/WarmSiteController.php`, `app/Models/WarmSite.php`, `app/Models/WarmRun.php`

**Docker:** All artisan/phpunit commands via `docker compose exec app`

---

### Task 1: Custom Headers

Add optional custom HTTP headers to warming requests. Users configure key/value pairs per warm site.

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_custom_headers_to_warm_sites_table.php`
- Modify: `app/Models/WarmSite.php` — add `custom_headers` to fillable + casts
- Modify: `app/Services/WarmingService.php` — accept + merge custom headers in `warmUrl()`
- Modify: `app/Http/Requests/StoreWarmSiteRequest.php` — add validation for custom_headers
- Modify: `app/Http/Requests/UpdateWarmSiteRequest.php` — same
- Modify: `app/Http/Controllers/WarmSiteController.php` — pass custom_headers in show/edit props
- Modify: `app/Jobs/RunWarmSite.php` — pass custom_headers to warmUrl()
- Modify: `resources/js/Pages/CacheWarming/Create.vue` — add collapsible headers section
- Modify: `resources/js/Pages/CacheWarming/Edit.vue` — same
- Test: `tests/Feature/Services/WarmingServiceTest.php` — add test for custom headers
- Test: `tests/Feature/Http/Controllers/WarmSiteControllerTest.php` — add validation test

**Implementation details:**

Migration:
```php
$table->json('custom_headers')->nullable()->after('max_urls');
```

WarmSite model — add to fillable and casts:
```php
'custom_headers' => 'array',
```

WarmingService — change `warmUrl` signature:
```php
public function warmUrl(string $url, array $customHeaders = []): WarmUrlResult
```

Merge headers (block dangerous ones):
```php
$blockedHeaders = ['host', 'cookie', 'content-length', 'transfer-encoding', 'connection', 'x-forwarded-for', 'x-real-ip'];
$safeHeaders = collect($customHeaders)
    ->reject(fn ($v, $k) => in_array(strtolower($k), $blockedHeaders))
    ->all();

Http::withHeaders(array_merge($defaultHeaders, $safeHeaders))->get($url);
```

RunWarmSite — pass headers from warmSite:
```php
$customHeaders = $this->warmSite->custom_headers ?? [];
// In the loop:
$result = $warmingService->warmUrl($url, $customHeaders);
```

Validation rules:
```php
'custom_headers' => 'nullable|array|max:10',
'custom_headers.*.key' => 'required|string|max:255',
'custom_headers.*.value' => 'required|string|max:1000',
```

Vue UI — collapsible key/value pairs in Create/Edit forms:
- "Custom Headers" section with toggle
- Dynamic add/remove rows (key input + value input + remove button)
- Max 10 headers

**Commit:** `feat(warming): add custom headers support for warming requests.`

---

### Task 2: Sitemap Index Support

Handle `<sitemapindex>` documents that link to multiple child sitemaps.

**Files:**
- Modify: `app/Services/WarmingService.php` — detect sitemapindex and recursively fetch children
- Test: `tests/Feature/Services/WarmingServiceTest.php` — add sitemap index tests

**Implementation details:**

In `parseSitemap()`, after XML parsing, detect the root element:
```php
// Check if this is a sitemap index (contains <sitemap> elements, not <url>)
$sitemapLocs = $xml->xpath('//sm:sitemap/sm:loc') ?: $xml->xpath('//sitemap/loc') ?: [];

if (!empty($sitemapLocs)) {
    // This is a sitemap index — recursively fetch each child sitemap
    $allUrls = [];
    foreach ($sitemapLocs as $childLoc) {
        $childUrl = trim((string) $childLoc);
        $childUrls = $this->parseSitemap($childUrl);
        $allUrls = array_merge($allUrls, $childUrls);

        // Safety cap: stop if we already have enough URLs
        if (count($allUrls) > 10000) {
            break;
        }
    }
    return $allUrls;
}

// Otherwise parse as regular sitemap
$elements = $xml->xpath('//sm:url/sm:loc') ?: $xml->xpath('//url/loc') ?: [];
```

Add recursion depth limit (max 2 levels) to prevent infinite loops:
```php
private function parseSitemap(string $sitemapUrl, int $depth = 0): array
{
    if ($depth > 2) {
        Log::warning('WarmingService: sitemap recursion depth exceeded', ['url' => $sitemapUrl]);
        return [];
    }
    // ... existing code ...
    // For child sitemaps:
    $childUrls = $this->parseSitemap($childUrl, $depth + 1);
}
```

Tests:
```php
test_resolve_urls_from_sitemap_index()
// Http::fake a sitemap index XML pointing to 2 child sitemaps, each with URLs
// Verify all URLs from both children are returned

test_sitemap_index_respects_depth_limit()
// Sitemap index pointing to another sitemap index pointing to a third — verify only 2 levels deep
```

**Commit:** `feat(warming): add sitemap index (sitemapindex) support with recursion depth limit.`

---

### Task 3: Per-URL Detail

Store individual URL results per run for debugging.

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_warm_run_urls_table.php`
- Create: `app/Models/WarmRunUrl.php`
- Create: `database/factories/WarmRunUrlFactory.php`
- Modify: `app/Models/WarmRun.php` — add `warmRunUrls()` relationship
- Modify: `app/Jobs/RunWarmSite.php` — store each URL result
- Modify: `app/Http/Controllers/WarmSiteController.php` — add `runDetail()` method
- Modify: `routes/web.php` — add route for run detail
- Create: `resources/js/Pages/CacheWarming/RunDetail.vue` — per-URL table
- Modify: `resources/js/Pages/CacheWarming/Show.vue` — link run rows to detail page
- Test: `tests/Feature/Jobs/RunWarmSiteTest.php` — verify URL records created

**Migration:**
```php
Schema::create('warm_run_urls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warm_run_id')->constrained()->cascadeOnDelete();
    $table->string('url', 2048);
    $table->unsignedSmallInteger('status_code')->default(0);
    $table->string('cache_status', 20); // hit, miss, unknown
    $table->unsignedSmallInteger('response_time_ms')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index('warm_run_id');
});
```

**WarmRunUrl model:**
```php
class WarmRunUrl extends Model
{
    use HasFactory;

    protected $fillable = ['warm_run_id', 'url', 'status_code', 'cache_status', 'response_time_ms', 'error_message'];
    protected $casts = ['status_code' => 'integer', 'response_time_ms' => 'integer'];

    public function warmRun(): BelongsTo { return $this->belongsTo(WarmRun::class); }
}
```

**RunWarmSite** — after each warmUrl call, create a WarmRunUrl:
```php
WarmRunUrl::create([
    'warm_run_id' => $warmRun->id,
    'url' => $result->url,
    'status_code' => $result->statusCode,
    'cache_status' => $result->cacheStatus,
    'response_time_ms' => $result->responseTimeMs,
    'error_message' => $result->errorMessage,
]);
```

**Route:** `GET /warming/{warming}/runs/{warmRun}` → `runDetail()`

**RunDetail.vue:** DataTable with columns: URL (truncated), Status Code, Cache Status (badge), Response Time, Error

**Commit:** `feat(warming): add per-URL detail tracking for warm runs.`

---

### Task 4: Monitor Linking

Associate warm sites with existing monitors.

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_monitor_id_to_warm_sites_table.php`
- Modify: `app/Models/WarmSite.php` — add `monitor()` belongsTo, add to fillable
- Modify: `app/Models/Monitor.php` — add `warmSite()` hasOne
- Modify: `app/Http/Controllers/WarmSiteController.php` — pass monitors list for select
- Modify: `app/Http/Requests/StoreWarmSiteRequest.php` — add `monitor_id` validation
- Modify: `app/Http/Requests/UpdateWarmSiteRequest.php` — same
- Modify: `resources/js/Pages/CacheWarming/Create.vue` — add optional monitor select
- Modify: `resources/js/Pages/CacheWarming/Edit.vue` — same
- Modify: `resources/js/Pages/CacheWarming/Show.vue` — show linked monitor with link
- Modify: `resources/js/Pages/Monitors/Show.vue` — show warming status if linked

**Migration:**
```php
$table->foreignId('monitor_id')->nullable()->constrained()->nullOnDelete()->after('team_id');
```

**Validation:**
```php
'monitor_id' => 'nullable|exists:monitors,id',
```

**Controller** — pass monitors for dropdowns:
```php
'monitors' => Monitor::select('id', 'name', 'url')->orderBy('name')->get(),
```

**Show page** — display linked monitor info with link to `/monitors/{id}`.

**Monitor Show page** — if monitor has a warmSite, show a card with latest warming stats and link to `/warming/{id}`.

**Commit:** `feat(warming): add optional monitor linking for warm sites.`

---

### Task 5: Hit Ratio Chart

Add historical hit ratio trend chart to the Show page.

**Files:**
- Create: `resources/js/Components/WarmingHitRatioChart.vue` — SVG line chart
- Modify: `app/Http/Controllers/WarmSiteController.php` — add `chartData` prop to show()
- Modify: `resources/js/Pages/CacheWarming/Show.vue` — include chart component

**Controller** — compute chart data:
```php
'chartData' => $warming->warmRuns()
    ->where('status', 'completed')
    ->orderBy('started_at')
    ->limit(50)
    ->get(['urls_total', 'urls_hit', 'started_at'])
    ->map(fn ($run) => [
        'date' => $run->started_at->toIso8601String(),
        'hit_ratio' => $run->urls_total > 0
            ? round(($run->urls_hit / $run->urls_total) * 100, 1)
            : 0,
    ]),
```

**WarmingHitRatioChart.vue** — follow ResponseTimeChart.vue pattern:
- SVG 800×200 with padding
- Y-axis: 0–100% with grid lines at 25, 50, 75, 100
- X-axis: dates from data
- Line connecting data points, colored by zone (green >80, yellow >50, red <50)
- Hover tooltip showing date + hit ratio %
- Area fill below line with gradient

**Commit:** `feat(warming): add hit ratio trend chart to warm site show page.`

---

### Task 6: Real-Time Reverb Progress

Broadcast warming progress via Reverb WebSocket so the Show page updates live during a "Warm Now" run.

**Files:**
- Create: `app/Events/WarmRunProgress.php` — broadcast event
- Modify: `app/Jobs/RunWarmSite.php` — broadcast progress after each URL batch
- Modify: `resources/js/Composables/useRealtimeUpdates.ts` — add `onWarmRunProgress` handler
- Modify: `resources/js/Pages/CacheWarming/Show.vue` — listen for progress + auto-reload
- Modify: `resources/js/Pages/CacheWarming/Index.vue` — listen for completion + auto-reload

**WarmRunProgress event** — follow MonitorChecked pattern:
```php
class WarmRunProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $teamId,
        public int $warmSiteId,
        public int $warmRunId,
        public int $urlsProcessed,
        public int $urlsTotal,
        public int $hits,
        public int $misses,
        public int $errors,
        public bool $completed = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("team.{$this->teamId}")];
    }

    public function broadcastAs(): string
    {
        return 'warm.run.progress';
    }
}
```

**RunWarmSite** — broadcast every 5 URLs (not every URL — too noisy):
```php
if (($index + 1) % 5 === 0 || $index === count($urls) - 1) {
    WarmRunProgress::dispatch(
        $this->warmSite->team_id,
        $this->warmSite->id,
        $warmRun->id,
        $index + 1,
        count($urls),
        $hits,
        $misses,
        $errors,
    );
}
```

After run completes, broadcast final event with `completed: true`.

**useRealtimeUpdates** — add handler:
```typescript
onWarmRunProgress: string[]  // props to reload when warming completes
```

Listen for `.warm.run.progress` event. On `completed: true` → reload specified props. During progress → update a reactive progress object (no full reload).

**Show.vue** — show progress bar during active run:
```html
<div v-if="warmingProgress" class="glass p-4">
    <div class="flex justify-between text-sm text-zinc-400 mb-2">
        <span>Warming in progress...</span>
        <span>{{ warmingProgress.urlsProcessed }} / {{ warmingProgress.urlsTotal }}</span>
    </div>
    <div class="w-full bg-white/5 rounded-full h-2">
        <div class="bg-emerald-500 h-2 rounded-full transition-all" :style="{ width: progressPct + '%' }"></div>
    </div>
</div>
```

**Commit:** `feat(warming): add real-time Reverb progress for warming runs.`

---

### Task 7: Pruning Job

Automatically clean up old warm_runs and warm_run_urls records.

**Files:**
- Create: `app/Jobs/PruneWarmRuns.php`
- Modify: `routes/console.php` — schedule daily
- Test: `tests/Feature/Jobs/PruneWarmRunsTest.php`

**PruneWarmRuns job:**
```php
class PruneWarmRuns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $retentionDays = (int) config('warming.retention_days', 180);

        $deleted = WarmRun::where('created_at', '<', now()->subDays($retentionDays))->delete();

        if ($deleted > 0) {
            Log::info('PruneWarmRuns: deleted old warm runs', ['count' => $deleted, 'retention_days' => $retentionDays]);
        }
    }
}
```

Note: `warm_run_urls` cascade-deletes with `warm_runs` (FK constraint).

**Scheduler:**
```php
Schedule::job(new PruneWarmRuns)->daily()->withoutOverlapping();
```

**Config** — add to `config/warming.php`:
```php
return [
    'retention_days' => env('WARMING_RETENTION_DAYS', 180),
];
```

**Test:**
```php
test_deletes_runs_older_than_retention()
// Create run 200 days old, run 10 days old, execute job, verify old deleted, recent kept

test_cascades_to_warm_run_urls()
// Create run with URL details, prune, verify both gone
```

**Commit:** `feat(warming): add PruneWarmRuns job with configurable retention (180 days default).`

---

### Task 8: Failure Notifications

Notify team when a warming run fails (3+ consecutive errors or sitemap unreachable).

**Files:**
- Create: `app/Notifications/WarmRunFailedNotification.php`
- Modify: `app/Jobs/RunWarmSite.php` — trigger notification on failure
- Modify: `app/Services/NotificationService.php` — add `notifyWarmingFailed()` method (with cooldown)
- Test: `tests/Feature/Jobs/RunWarmSiteTest.php` — verify notification dispatched on failure

**WarmRunFailedNotification** — follow existing notification pattern:
```php
class WarmRunFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WarmSite $warmSite,
        public WarmRun $warmRun,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Cache Warming Failed: {$this->warmSite->name}")
            ->line("The warming run for {$this->warmSite->domain} failed.")
            ->line("Error: {$this->warmRun->error_message}")
            ->action('View Details', url("/warming/{$this->warmSite->id}"));
    }
}
```

**NotificationService** — add method with 1-hour cooldown:
```php
public function notifyWarmingFailed(WarmSite $warmSite, WarmRun $warmRun): void
{
    $key = "notify:warming:{$warmSite->id}:failed";

    if (Cache::has($key)) {
        return;
    }

    Cache::put($key, true, now()->addHour());

    $warmSite->team->owner->notify(new WarmRunFailedNotification($warmSite, $warmRun));
}
```

**RunWarmSite** — at end, if there are errors or early stop:
```php
if ($errors > 0 && $errorMessage) {
    app(NotificationService::class)->notifyWarmingFailed($this->warmSite, $warmRun);
}
```

Also in the `failed()` method:
```php
public function failed(Throwable $e): void
{
    Log::error('RunWarmSite job failed', [...]);

    // Create a failed WarmRun record for visibility
    $run = WarmRun::create([
        'warm_site_id' => $this->warmSite->id,
        'status' => WarmRunStatus::FAILED,
        'error_message' => $e->getMessage(),
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    app(NotificationService::class)->notifyWarmingFailed($this->warmSite, $run);
}
```

**Commit:** `feat(warming): add failure notifications with 1-hour cooldown.`

---

## Execution Order

Tasks are mostly independent but have one dependency:

1. **Task 1** (Custom Headers) — independent
2. **Task 2** (Sitemap Index) — independent
3. **Task 3** (Per-URL Detail) — independent, creates `warm_run_urls` table
4. **Task 4** (Monitor Linking) — independent
5. **Task 5** (Hit Ratio Chart) — independent
6. **Task 6** (Real-Time Progress) — benefits from Task 3 being done first (can show per-URL progress)
7. **Task 7** (Pruning Job) — should run after Task 3 (needs to know about warm_run_urls cascade)
8. **Task 8** (Failure Notifications) — independent

**Parallelizable groups:**
- Group A (backend-only): Tasks 1, 2, 7, 8
- Group B (backend + frontend): Tasks 3, 4, 5, 6

## Summary

| Task | Feature | New Files | Modified Files |
|------|---------|-----------|----------------|
| 1 | Custom Headers | 1 migration | 6 (model, service, requests, controller, 2 Vue) |
| 2 | Sitemap Index | 0 | 2 (service, test) |
| 3 | Per-URL Detail | 4 (migration, model, factory, Vue page) | 5 (model, job, controller, routes, Vue) |
| 4 | Monitor Linking | 1 migration | 7 (2 models, requests, controller, 3 Vue) |
| 5 | Hit Ratio Chart | 1 Vue component | 2 (controller, Vue) |
| 6 | Real-Time Progress | 1 event | 4 (job, composable, 2 Vue) |
| 7 | Pruning Job | 2 (job, config) | 2 (scheduler, test) |
| 8 | Failure Notifications | 1 notification | 3 (job, service, test) |
