# Plan: Tier 3 + Tier 4 + Severity + PWA Advanced

## Context

Large batch of improvements covering backend performance, UX features, incident management, and PWA. Organized into 6 independent work streams for parallel multi-agent execution.

## Work Streams

### Stream A — Backend Performance (laravel-expert)
**Files to modify:**
- `app/Services/MetricsService.php` — Fix O(n²) query + add p95/p99 + add SLA metrics
- `app/Services/CheckService.php` — Call `MetricsService::invalidateCache()` on incident create/resolve
- `docker-compose.yml` — Add resource limits

**Changes:**

**A1. MetricsService query optimization (#13)**
Lines 33-44: The `MAX(id)` subquery runs across ALL teams. Fix by scoping to active monitor IDs:
```php
$activeMonitorIds = Monitor::active()->pluck('id');
$latestChecks = MonitorCheck::query()
    ->select('monitor_id', 'status')
    ->whereIn('monitor_id', $activeMonitorIds)
    ->whereIn('id', function ($q) use ($activeMonitorIds) {
        $q->select(DB::raw('MAX(id)'))
            ->from('monitor_checks')
            ->whereIn('monitor_id', $activeMonitorIds)
            ->groupBy('monitor_id');
    })
    ->get();
```
Then remove the in-memory filtering on lines 43-44.

**A2. Cache invalidation (#14)**
`MetricsService::invalidateCache($teamId)` already exists (line 23-26) but is NEVER called.
In `CheckService::check()`, after incident create (line 66) and resolve (line 104), add:
```php
MetricsService::invalidateCache($monitor->team_id);
```

**A3. p95/p99 response times (#20)**
Add to `computeDashboardMetrics()`:
```php
$percentiles = MonitorCheck::where('checked_at', '>=', now()->subDay())
    ->selectRaw("PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) as p95")
    ->selectRaw("PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY response_time_ms) as p99")
    ->first();
```
Return `p95_response_time_24h` and `p99_response_time_24h` in metrics array.

**A4. SLA metrics (#19 — backend part)**
Add to `computeDashboardMetrics()`: compute `sla_target` (from team settings or default 99.9%), `sla_current_month` (uptime % since start of month).
Requires reading team's sla_target config (add to Team model or use config default).

**A5. Docker resource limits (#18)**
Add to each service in docker-compose.yml:
```yaml
deploy:
  resources:
    limits:
      memory: 512M  # app: 512M, worker: 256M, scheduler: 128M, reverb: 128M
```

---

### Stream B — Notification System Refactor (laravel-expert)
**Files to create:**
- `app/Jobs/Notifications/SendEmailNotification.php`
- `app/Jobs/Notifications/SendWebhookNotification.php`
- `app/Jobs/Notifications/SendSlackNotification.php`
- `app/Jobs/Notifications/SendDiscordNotification.php`
- `app/Jobs/Notifications/SendPushNotification.php`
- `app/Jobs/Notifications/SendTelegramNotification.php`
- `app/Jobs/Notifications/BaseNotificationJob.php` (abstract)
- `app/Models/NotificationLog.php`
- `database/migrations/xxxx_create_notification_logs_table.php`

**Files to modify:**
- `app/Services/NotificationService.php` — Dispatch per-channel jobs instead of monolithic SendNotification
- `app/Jobs/SendNotification.php` — Keep for backwards compat but deprecate (or delete)

**Changes:**

**B1. Split SendNotification (#15)**
Create abstract `BaseNotificationJob` with shared `buildPayload()`, `$tries=3`, `$backoff=[30,60,120]`, `onQueue('notifications')`.
Each channel job extends it and implements `handle()` for its specific channel.
NotificationService dispatches the correct job class based on `$channel->type`.

**B2. Notification logs table (#23)**
```php
Schema::create('notification_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
    $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('monitor_incident_id')->nullable()->constrained('monitor_incidents')->nullOnDelete();
    $table->string('event'); // 'down', 'up'
    $table->string('channel_type'); // 'email', 'slack', etc.
    $table->string('status'); // 'sent', 'failed', 'skipped'
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at');
    $table->index(['monitor_id', 'sent_at']);
    $table->index(['notification_channel_id', 'sent_at']);
});
```
Each channel job logs success/failure to this table.

---

### Stream C — Database Migrations & Models (database-expert)
**Files to create:**
- `database/migrations/xxxx_add_severity_and_notes_to_monitor_incidents_table.php`
- `database/migrations/xxxx_create_notification_logs_table.php` (from Stream B)
- `database/migrations/xxxx_add_sla_target_to_teams_table.php`
- `app/Enums/IncidentSeverity.php`

**Files to modify:**
- `app/Models/MonitorIncident.php` — Add severity, notes fields + casts
- `app/Models/NotificationLog.php` — New model
- `app/Models/Team.php` — Add sla_target field
- `app/Enums/IncidentCause.php` — No changes needed (already complete)

**Changes:**

**C1. Incident severity (#31 + #22)**
```php
// Migration
$table->string('severity')->default('major'); // critical, major, minor, warning
$table->text('notes')->nullable(); // post-mortem notes
```
New enum `IncidentSeverity`:
```php
enum IncidentSeverity: string {
    case CRITICAL = 'critical';
    case MAJOR = 'major';
    case MINOR = 'minor';
    case WARNING = 'warning';
}
```
Auto-assign in `CheckService`: CRITICAL for timeout/error, MAJOR for status_code/keyword, MINOR for ssl, WARNING for functional.

**C2. SLA target on teams**
```php
$table->decimal('sla_target', 5, 2)->default(99.90);
```

**C3. NotificationLog model**
Standard Eloquent model with belongsTo Monitor, NotificationChannel, MonitorIncident.

---

### Stream D — Frontend Dashboard & Monitors UX (frontend-expert)
**Files to modify:**
- `resources/js/Pages/Dashboard.vue` — Add SLA progress bar, p95/p99 metrics, time range selector
- `resources/js/Pages/Monitors/Index.vue` — Add bulk actions (checkboxes, select-all, bulk pause/resume/delete)
- `resources/js/Pages/Monitors/Show.vue` — Add incident severity badges, post-mortem notes editor
- `resources/js/Pages/Incidents/Index.vue` — Add severity column with color-coded badges
- `resources/js/Components/StatusBadge.vue` — Extend for severity display
- `resources/js/Composables/useRealtimeUpdates.ts` — Add granular monitor status update handler

**Files to create:**
- `resources/js/Components/SlaProgressBar.vue`
- `resources/js/Components/SeverityBadge.vue`
- `resources/js/Components/BulkActionBar.vue`

**Changes:**

**D1. Dashboard SLA + p95/p99 (#19, #20)**
Add SLA progress bar component showing target vs actual with color gradient.
Add p95/p99 cards next to avg response time.
Backend already provides the data (from Stream A).

**D2. Dashboard time range** (NOT in scope — dashboard is 24h aggregated, monitor/show already has period selector)
Skip — the monitor show page already has 1h/24h/7d/1mo/3mo/6mo selector.

**D3. Bulk actions monitors (#24)**
Add checkbox column on Monitors/Index. When any checked: show floating action bar with Pause/Resume/Delete buttons.
Backend route: `POST /monitors/bulk-action` with `{ action: 'pause'|'resume'|'delete', ids: [...] }`.
Add `MonitorController::bulkAction()` method.

**D4. Incident severity + post-mortems (#31, #22)**
On Incidents/Index: add severity column with color-coded SeverityBadge.
On Monitors/Show: show severity badge on each incident, add notes textarea on resolved incidents.
Backend: add `IncidentController::updateNotes()` route for saving post-mortem notes.

---

### Stream E — Real-time & Events (laravel-expert)
**Files to create:**
- `app/Events/IncidentCreated.php`
- `app/Events/IncidentResolved.php`

**Files to modify:**
- `app/Services/CheckService.php` — Dispatch IncidentCreated/IncidentResolved events
- `resources/js/Composables/useRealtimeUpdates.ts` — Handle granular updates + new events

**Changes:**

**E1. New broadcast events (#21)**
`IncidentCreated` broadcasts on `team.{teamId}` with incident data (id, monitor_id, cause, severity, started_at).
`IncidentResolved` broadcasts with incident data + resolved_at.
Both broadcastAs `.incident.created` / `.incident.resolved`.

**E2. Granular monitor updates**
Current `MonitorChecked` already sends check data in `broadcastWith()`. Frontend should use this data to update monitor status in-place instead of full Inertia reload.
Modify `useRealtimeUpdates` to accept an `onMonitorStatusUpdate` callback that receives the check data. Pages can use this to patch their local state before the debounced full reload.

**E3. Dispatch events from CheckService**
After incident create (line 66): `event(new IncidentCreated($incident))`.
After incident resolve (line 104): `event(new IncidentResolved($incident))`.

---

### Stream F — PWA Advanced + Offline (frontend-expert)
**Files to modify:**
- `public/sw.js` — Enhance with network-first + cache fallback for API, offline page
- `public/manifest.json` — Add screenshots, shortcuts, share_target
- `resources/js/Layouts/AppLayout.vue` — Add install prompt component
- `resources/js/app.ts` — Register enhanced service worker

**Files to create:**
- `resources/js/Components/InstallPrompt.vue`
- `resources/js/Composables/usePwaInstall.ts`
- `public/offline.html` — Offline fallback page (static HTML)

**Changes:**

**F1. Enhanced service worker**
Add strategies:
- Static assets: Cache-first (already done)
- Inertia page requests (`X-Inertia` header): Network-first, cache response on success, serve cached on network failure
- API requests (`/api/`): Network-first with 5s timeout, fallback to cache
- Offline fallback: If both network and cache miss, serve `/offline.html`

**F2. Offline page**
Simple branded HTML page saying "You are offline. Dashboard data may be outdated." with auto-retry.

**F3. Install prompt**
`usePwaInstall()` composable captures `beforeinstallprompt` event. Shows a dismissible banner in AppLayout when installable. Stores dismissal in localStorage for 7 days.

**F4. Manifest enhancements**
Add `screenshots`, `shortcuts` (Dashboard, Monitors), `share_target` for receiving shared URLs.

---

### Stream G — Notification History UI (frontend-expert)
**Files to create:**
- `resources/js/Pages/NotificationHistory/Index.vue`

**Files to modify:**
- `app/Http/Controllers/NotificationLogController.php` (new)
- `routes/web.php` — Add notification history route
- `resources/js/Layouts/AppLayout.vue` — Add nav link

**Changes:**
Add a page listing notification logs with filters (channel, monitor, event, status, date range).
Paginated table showing: timestamp, monitor name, channel name+type, event (up/down), status (sent/failed), error message.
Route: `GET /notifications` → `NotificationLogController::index()`.

---

## Dependency Graph

```
Stream C (migrations) ← must run FIRST (creates tables/columns)
    ↓
Stream A (MetricsService) ← depends on C for sla_target column
Stream B (Notifications) ← depends on C for notification_logs table
Stream E (Events) ← depends on C for severity column
    ↓
Stream D (Frontend Dashboard/Monitors) ← depends on A for new metrics data, C for severity
Stream F (PWA) ← independent, can run in parallel with anything
Stream G (Notification History UI) ← depends on B for notification_logs table
```

**Execution order:**
1. **Phase 1 (parallel):** Stream C (migrations) + Stream F (PWA)
2. **Phase 2 (parallel):** Stream A + Stream B + Stream E (all backend, after migrations)
3. **Phase 3 (parallel):** Stream D + Stream G (all frontend, after backend APIs ready)

## Verification Plan

1. `php artisan migrate` — Verify all migrations run cleanly
2. `vendor/bin/phpunit` — All existing tests pass
3. `vendor/bin/pint` — Code style clean
4. `npm run build` — Frontend builds without errors
5. Manual checks:
   - Dashboard shows SLA bar, p95/p99 metrics
   - Incidents show severity badges + notes field on resolved
   - Monitors/Index bulk select works (pause/resume/delete)
   - Notification logs page shows sent notifications
   - PWA install prompt appears on mobile
   - Offline fallback page loads when disconnected
   - WebSocket events update monitor status in real-time
   - New IncidentCreated/Resolved events broadcast correctly
6. `docker compose up` — All containers start with resource limits
