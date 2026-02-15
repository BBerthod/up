# Up — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an open-source, self-hostable uptime monitoring service with real-time dashboard, notifications, and public status pages.

**Architecture:** Laravel 11 monolith with Vue 3/Inertia frontend. Queue-based check execution (Redis), real-time updates via Laravel Reverb WebSockets. Multi-tenant via team_id scoping. Dockerized for Dokploy deployment.

**Tech Stack:** Laravel 11, PHP 8.4, Vue 3, Inertia.js, PostgreSQL 16, Redis 7, Laravel Reverb, Tailwind CSS, shadcn-vue, Sanctum

**Delegation:** All code generation tasks should be delegated to GLM (`delegate_to_glm`). Opus coordinates, reviews, and integrates.

---

## Phase 1: Project Scaffolding & Docker

### Task 1: Create Laravel project

**Files:**
- Create: entire Laravel 11 project in `/Users/billyberthod/Dev/up/`

**Step 1: Scaffold Laravel**

```bash
composer create-project laravel/laravel . --prefer-dist
```

**Step 2: Install core dependencies**

```bash
composer require inertiajs/inertia-laravel tightenco/ziggy laravel/reverb laravel/sanctum
npm install @inertiajs/vue3 vue @vitejs/plugin-vue tailwindcss @tailwindcss/vite
```

**Step 3: Install shadcn-vue**

```bash
npx shadcn-vue@latest init
```

**Step 4: Verify it runs**

```bash
php artisan serve
```
Expected: Laravel welcome page at localhost:8000

**Step 5: Commit**

```bash
git init && git add -A && git commit -m "chore: scaffold Laravel 11 with Vue 3, Inertia, Reverb, Tailwind, shadcn-vue"
```

---

### Task 2: Docker Compose (Dokploy-ready)

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `docker/nginx/default.conf`
- Create: `docker/php/php.ini`
- Create: `docker/supervisor/worker.conf`
- Create: `docker/supervisor/scheduler.conf`

**Step 1: Delegate to GLM — generate Dockerfile**

Multi-stage build: composer install + npm build, then PHP 8.4-FPM production image with required extensions (pdo_pgsql, redis, pcntl, bcmath).

**Step 2: Delegate to GLM — generate docker-compose.yml**

Services:
- `app` — Nginx + PHP-FPM (port 8000)
- `worker` — `php artisan queue:work redis --sleep=3 --tries=3`
- `scheduler` — `php artisan schedule:work`
- `reverb` — `php artisan reverb:start --host=0.0.0.0 --port=8080`
- `postgres` — PostgreSQL 16 (volume: pgdata)
- `redis` — Redis 7 (volume: redisdata)

Environment variables via `.env`. Dokploy-compatible (no build args, all env at runtime).

**Step 3: Delegate to GLM — generate nginx config**

Standard Laravel Nginx config with WebSocket proxy for `/app` (Reverb).

**Step 4: Test Docker build**

```bash
docker compose build
docker compose up -d
```
Expected: all 6 services running

**Step 5: Commit**

```bash
git add Dockerfile docker-compose.yml docker/ .dockerignore
git commit -m "chore: add Docker Compose setup (Dokploy-ready)"
```

---

### Task 3: Configure Inertia + Vue 3 + Tailwind + Design System

**Files:**
- Modify: `resources/js/app.js`
- Create: `resources/js/app.ts` (rename to TS)
- Modify: `resources/css/app.css`
- Create: `resources/js/Layouts/AppLayout.vue`
- Create: `resources/js/Layouts/GuestLayout.vue`
- Modify: `vite.config.js`
- Modify: `tailwind.config.js`
- Create: `resources/views/app.blade.php`

**Step 1: Delegate to GLM — Inertia middleware + app.blade.php**

Standard Inertia server-side setup with `HandleInertiaRequests` middleware and root Blade template. Include PWA manifest link, Inter + JetBrains Mono font imports.

**Step 2: Delegate to GLM — Vue 3 + Inertia client setup (app.ts)**

createInertiaApp with resolvePageComponent, use `../Layouts/AppLayout.vue` as default persistent layout.

**Step 3: Delegate to GLM — Tailwind config (Radiank design system)**

```
Colors:
  background: #0a0f1a
  surface: #111827
  border: rgba(255,255,255,0.08)
  primary: rgb(6, 182, 212) (cyan-400)
  success: rgb(16, 185, 129) (emerald-500)
  danger: rgb(239, 68, 68) (red-500)
  warning: rgb(245, 158, 11) (amber-500)
  text-primary: #f9fafb
  text-secondary: #9ca3af

Fonts:
  sans: Inter
  mono: JetBrains Mono

Dark mode: class-based (default dark)
```

**Step 4: Delegate to GLM — AppLayout.vue**

Sidebar navigation layout with glassmorphism cards. Nav items: Dashboard, Monitors, Notifications, Status Pages, Settings. User menu top-right.

**Step 5: Delegate to GLM — GuestLayout.vue**

Minimal centered layout for login/register pages.

**Step 6: Verify**

```bash
npm run dev
php artisan serve
```
Expected: app renders with dark navy background, correct fonts

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(ui): configure Inertia, Vue 3, Tailwind with Radiank design system"
```

---

## Phase 2: Auth & Multi-tenancy

### Task 4: Team model & multi-tenancy

**Files:**
- Create: `database/migrations/xxxx_create_teams_table.php`
- Create: `database/migrations/xxxx_add_team_id_to_users_table.php`
- Create: `app/Models/Team.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/Traits/BelongsToTeam.php`
- Create: `app/Models/Scopes/TeamScope.php`

**Step 1: Delegate to GLM — migrations**

`teams` table: id, name, timestamps.
Add `team_id` (foreignId, constrained) to users table.

**Step 2: Delegate to GLM — Team model**

Team has many users, monitors, notification channels, status pages.

**Step 3: Delegate to GLM — BelongsToTeam trait**

Trait that auto-applies TeamScope and auto-sets team_id on creating. Uses `auth()->user()->team_id`.

**Step 4: Delegate to GLM — TeamScope global scope**

Filters all queries by `team_id = auth()->user()->team_id`.

**Step 5: Run migrations**

```bash
php artisan migrate
```

**Step 6: Write tests**

Delegate to GLM — test that TeamScope filters correctly, test BelongsToTeam auto-sets team_id.

**Step 7: Run tests**

```bash
php artisan test --filter=Team
```
Expected: PASS

**Step 8: Commit**

```bash
git add -A && git commit -m "feat(auth): add Team model with multi-tenancy scope"
```

---

### Task 5: Auth pages (Login, Register)

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `app/Http/Controllers/Auth/RegisterController.php`
- Create: `resources/js/Pages/Auth/Login.vue`
- Create: `resources/js/Pages/Auth/Register.vue`
- Modify: `routes/web.php`

**Step 1: Delegate to GLM — controllers**

LoginController: show (Inertia render), store (authenticate, redirect /dashboard).
RegisterController: show, store (create team + user, login, redirect /dashboard).
On register, auto-create a Team with user's name.

**Step 2: Delegate to GLM — Login.vue and Register.vue**

Radiank design: glassmorphism card, centered on dark background. Form fields with shadcn-vue Input, Button components. Logo "Up" top center.

**Step 3: Add routes**

```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
```

**Step 4: Write tests**

Delegate to GLM — test register creates team + user, test login, test redirect.

**Step 5: Run tests & verify**

```bash
php artisan test --filter=Auth
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(auth): add login and register pages"
```

---

## Phase 3: Core Monitoring

### Task 6: Monitor model & migrations

**Files:**
- Create: `database/migrations/xxxx_create_monitors_table.php`
- Create: `database/migrations/xxxx_create_monitor_checks_table.php`
- Create: `database/migrations/xxxx_create_monitor_incidents_table.php`
- Create: `app/Models/Monitor.php`
- Create: `app/Models/MonitorCheck.php`
- Create: `app/Models/MonitorIncident.php`

**Step 1: Delegate to GLM — all 3 migrations**

See data model from design doc. monitors uses BelongsToTeam. Indexes on:
- `monitor_checks(monitor_id, checked_at)` — query performance
- `monitor_checks(checked_at)` — cleanup old data
- `monitors(team_id, is_active)` — dispatch checks query

**Step 2: Delegate to GLM — all 3 models**

Monitor: BelongsToTeam, hasMany checks/incidents, casts (is_active: boolean, method: enum).
MonitorCheck: belongsTo monitor, cast checked_at as datetime.
MonitorIncident: belongsTo monitor, scope `active()` for unresolved.

**Step 3: Run migrations**

```bash
php artisan migrate
```

**Step 4: Write model tests**

Delegate to GLM — test relationships, test scopes, test casts.

**Step 5: Run tests**

```bash
php artisan test --filter=Monitor
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(monitors): add Monitor, MonitorCheck, MonitorIncident models and migrations"
```

---

### Task 7: CheckService — core HTTP check logic

**Files:**
- Create: `app/Services/CheckService.php`
- Create: `tests/Feature/Services/CheckServiceTest.php`

**Step 1: Write failing tests**

Delegate to GLM — test cases:
- `test_check_returns_up_for_200_response`
- `test_check_returns_down_for_500_response`
- `test_check_returns_down_for_timeout`
- `test_check_detects_keyword_present`
- `test_check_returns_down_when_keyword_missing`
- `test_check_captures_ssl_expiry`
- `test_check_captures_response_time`
- `test_check_creates_incident_on_state_change_to_down`
- `test_check_resolves_incident_on_state_change_to_up`

Use Http::fake() for mocking.

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=CheckService
```
Expected: FAIL

**Step 3: Delegate to GLM — implement CheckService**

```php
class CheckService
{
    public function check(Monitor $monitor): MonitorCheck
    {
        // 1. Execute HTTP request (with timeout, capture timing)
        // 2. Check status code against expected
        // 3. Check keyword if set
        // 4. Extract SSL expiry from certificate
        // 5. Create MonitorCheck record
        // 6. Handle state changes (create/resolve incidents)
        // 7. Update monitor.last_checked_at
        // 8. Return MonitorCheck
    }
}
```

Uses `Http::timeout(30)->withOptions(['verify' => true])`. Captures SSL via stream context.

**Step 4: Run tests**

```bash
php artisan test --filter=CheckService
```
Expected: ALL PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat(monitoring): add CheckService with HTTP check, keyword, SSL, incidents"
```

---

### Task 8: Jobs — DispatchChecks + RunCheck

**Files:**
- Create: `app/Jobs/DispatchChecks.php`
- Create: `app/Jobs/RunCheck.php`
- Create: `app/Events/MonitorChecked.php`
- Modify: `routes/console.php` (scheduler)
- Create: `tests/Feature/Jobs/DispatchChecksTest.php`
- Create: `tests/Feature/Jobs/RunCheckTest.php`

**Step 1: Delegate to GLM — DispatchChecks job**

Queries monitors where `is_active = true` AND (`last_checked_at IS NULL` OR `last_checked_at <= now() - interval minutes`). Dispatches RunCheck for each.

**Step 2: Delegate to GLM — RunCheck job**

Calls `CheckService::check($monitor)`. Broadcasts `MonitorChecked` event via Reverb. Handles exceptions (logs, marks as down).

**Step 3: Delegate to GLM — MonitorChecked event**

Implements ShouldBroadcast. Broadcasts on `private-team.{teamId}` channel. Payload: monitor id, status, response_time_ms, checked_at.

**Step 4: Register scheduler**

```php
// routes/console.php
Schedule::job(new DispatchChecks)->everyMinute();
```

**Step 5: Write tests**

Delegate to GLM:
- DispatchChecks only dispatches monitors due for check
- RunCheck calls CheckService and broadcasts event
- RunCheck handles exceptions gracefully

**Step 6: Run tests**

```bash
php artisan test --filter=DispatchChecks
php artisan test --filter=RunCheck
```

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(monitoring): add DispatchChecks, RunCheck jobs with scheduler"
```

---

## Phase 4: Notification System

### Task 9: Notification channels model & service

**Files:**
- Create: `database/migrations/xxxx_create_notification_channels_table.php`
- Create: `database/migrations/xxxx_create_monitor_notification_channel_table.php`
- Create: `app/Models/NotificationChannel.php`
- Create: `app/Services/NotificationService.php`
- Create: `app/Notifications/MonitorDownNotification.php`
- Create: `app/Notifications/MonitorUpNotification.php`
- Create: `app/Jobs/SendNotification.php`

**Step 1: Delegate to GLM — migrations**

`notification_channels`: id, team_id, type (enum), settings (jsonb), is_active, timestamps.
`monitor_notification_channel` pivot: monitor_id, notification_channel_id.

**Step 2: Delegate to GLM — NotificationChannel model**

BelongsToTeam. Casts: settings as array, type as enum. belongsToMany monitors.

**Step 3: Delegate to GLM — NotificationService**

```php
class NotificationService
{
    public function notifyDown(Monitor $monitor, MonitorIncident $incident, MonitorCheck $check): void
    public function notifyUp(Monitor $monitor, MonitorIncident $incident, MonitorCheck $check): void
    // Gets monitor's notification channels, dispatches SendNotification for each
}
```

**Step 4: Delegate to GLM — notification classes**

Each notification type (email, webhook, slack, discord) as a strategy:
- Email: Laravel Mailable with clean template
- Webhook: HTTP POST with JSON payload (see design doc)
- Slack: Slack webhook with formatted message
- Discord: Discord webhook with embed

**Step 5: Delegate to GLM — SendNotification job**

Dispatches the actual notification via the appropriate channel strategy.

**Step 6: Wire into CheckService**

After state change in CheckService, call NotificationService.

**Step 7: Write tests**

Delegate to GLM — test each channel type sends correctly (mock HTTP for webhooks).

**Step 8: Run tests**

```bash
php artisan test --filter=Notification
```

**Step 9: Commit**

```bash
git add -A && git commit -m "feat(notifications): add email, webhook, Slack, Discord notification channels"
```

---

## Phase 5: Web Dashboard (Inertia + Vue)

### Task 10: Monitor CRUD pages

**Files:**
- Create: `app/Http/Controllers/MonitorController.php`
- Create: `resources/js/Pages/Monitors/Index.vue`
- Create: `resources/js/Pages/Monitors/Show.vue`
- Create: `resources/js/Pages/Monitors/Create.vue`
- Create: `resources/js/Pages/Monitors/Edit.vue`
- Modify: `routes/web.php`

**Step 1: Delegate to GLM — MonitorController**

Standard resource controller with Inertia responses. Index passes monitors with latest check status, uptime %. Show passes monitor with checks (paginated), incidents, response time data for chart.

**Step 2: Delegate to GLM — Index.vue (Dashboard)**

Monitor list as glassmorphism cards. Each card: name, URL, status dot (green/red, pulsing), uptime %, avg response time. Filter tabs: All / Up / Down / Paused. "Add Monitor" button.

**Step 3: Delegate to GLM — Show.vue (Monitor Detail)**

Response time line chart (Chart.js or lightweight alternative). 90-day uptime bar. Incident history list. Pause/Resume/Edit/Delete actions.

**Step 4: Delegate to GLM — Create.vue & Edit.vue**

Form with shadcn-vue components: URL input, name, method select, interval select, expected status code, keyword (optional), notification channels (multi-select).

**Step 5: Add routes**

```php
Route::middleware('auth')->group(function () {
    Route::resource('monitors', MonitorController::class);
    Route::post('monitors/{monitor}/pause', [MonitorController::class, 'pause'])->name('monitors.pause');
    Route::post('monitors/{monitor}/resume', [MonitorController::class, 'resume'])->name('monitors.resume');
});
```

**Step 6: Verify in browser**

Navigate to /monitors, create a monitor, verify it appears.

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(ui): add monitor CRUD pages with dashboard"
```

---

### Task 11: Notification channels CRUD pages

**Files:**
- Create: `app/Http/Controllers/NotificationChannelController.php`
- Create: `resources/js/Pages/NotificationChannels/Index.vue`
- Create: `resources/js/Pages/NotificationChannels/Create.vue`
- Create: `resources/js/Pages/NotificationChannels/Edit.vue`

**Step 1: Delegate to GLM — controller + pages**

Resource controller. Form dynamically changes based on selected type:
- Email: just email address
- Webhook: URL
- Slack: webhook URL
- Discord: webhook URL
- Push: auto-subscribe via browser Push API

**Step 2: Add routes, verify, commit**

```bash
git add -A && git commit -m "feat(ui): add notification channel management pages"
```

---

### Task 12: Real-time WebSocket integration

**Files:**
- Modify: `resources/js/app.ts` (add Echo)
- Create: `resources/js/Composables/useMonitorUpdates.ts`
- Modify: `resources/js/Pages/Monitors/Index.vue`
- Modify: `routes/channels.php`

**Step 1: Install Laravel Echo**

```bash
npm install laravel-echo pusher-js
```

**Step 2: Delegate to GLM — Echo setup in app.ts**

Configure Echo with Reverb (WebSocket) connection.

**Step 3: Delegate to GLM — useMonitorUpdates composable**

Listens on `private-team.{teamId}` for `MonitorChecked` events. Updates reactive monitor data in place. Triggers pulse animation on status dot.

**Step 4: Delegate to GLM — channel authorization**

```php
// routes/channels.php
Broadcast::channel('team.{teamId}', fn (User $user, int $teamId) => $user->team_id === $teamId);
```

**Step 5: Wire into Index.vue**

Use `useMonitorUpdates()` composable to live-update monitor cards.

**Step 6: Test manually**

Run worker + reverb, create a monitor, watch it update in real-time.

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(realtime): add WebSocket live updates via Reverb"
```

---

## Phase 6: Status Pages

### Task 13: Status page model & public page

**Files:**
- Create: `database/migrations/xxxx_create_status_pages_table.php`
- Create: `database/migrations/xxxx_create_status_page_monitor_table.php`
- Create: `app/Models/StatusPage.php`
- Create: `app/Http/Controllers/StatusPageController.php`
- Create: `app/Http/Controllers/PublicStatusPageController.php`
- Create: `resources/js/Pages/StatusPages/Index.vue`
- Create: `resources/js/Pages/StatusPages/Create.vue`
- Create: `resources/js/Pages/StatusPages/Edit.vue`
- Create: `resources/js/Pages/StatusPages/Public.vue`
- Create: `resources/js/Layouts/StatusPageLayout.vue`

**Step 1: Delegate to GLM — migrations + model**

StatusPage: BelongsToTeam, belongsToMany monitors (with sort_order pivot).

**Step 2: Delegate to GLM — CRUD controller + pages**

Manage status pages, select which monitors to show, set slug.

**Step 3: Delegate to GLM — PublicStatusPageController**

No auth required. Loads status page by slug with monitors, current status, 90-day uptime data.

**Step 4: Delegate to GLM — Public.vue with StatusPageLayout**

Clean public page. Dark/Light mode toggle. Monitor list with status dots and 90-day uptime bars. Active incidents section. Branded with "Powered by Up".

**Step 5: Add routes**

```php
// Public (no auth)
Route::get('/status/{slug}', [PublicStatusPageController::class, 'show'])->name('status.show');

// Management (auth)
Route::resource('status-pages', StatusPageController::class)->middleware('auth');
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(status-pages): add public status pages with dark/light toggle"
```

---

## Phase 7: REST API

### Task 14: API controllers + Sanctum

**Files:**
- Create: `app/Http/Controllers/Api/MonitorApiController.php`
- Create: `app/Http/Controllers/Api/NotificationChannelApiController.php`
- Create: `app/Http/Controllers/Api/StatusPageApiController.php`
- Create: `app/Http/Resources/MonitorResource.php`
- Create: `app/Http/Resources/MonitorCheckResource.php`
- Create: `app/Http/Resources/NotificationChannelResource.php`
- Create: `app/Http/Resources/StatusPageResource.php`
- Modify: `routes/api.php`

**Step 1: Delegate to GLM — API resources (JSON transformers)**

Standard Laravel API Resources for each model.

**Step 2: Delegate to GLM — API controllers**

Mirror web controllers but return JSON. Sanctum `auth:sanctum` middleware.

**Step 3: Delegate to GLM — API routes**

All routes from design doc, grouped under `auth:sanctum` middleware.

**Step 4: Delegate to GLM — API token management page**

Add to Team Settings: create/revoke API tokens via Sanctum.

**Step 5: Write API tests**

Delegate to GLM — test all CRUD endpoints, test auth, test team scoping.

**Step 6: Run tests**

```bash
php artisan test --filter=Api
```

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(api): add REST API with Sanctum auth"
```

---

## Phase 8: PWA

### Task 15: PWA setup + Push notifications

**Files:**
- Create: `public/manifest.json`
- Create: `public/sw.js`
- Create: `resources/js/Composables/usePushNotifications.ts`
- Create: `app/Http/Controllers/PushSubscriptionController.php`
- Create: `database/migrations/xxxx_create_push_subscriptions_table.php`

**Step 1: Delegate to GLM — manifest.json**

App name "Up", theme color #0a0f1a, icons, display: standalone.

**Step 2: Delegate to GLM — service worker**

Cache app shell (offline support), handle push events (show notification).

**Step 3: Delegate to GLM — push subscription flow**

- Frontend: `usePushNotifications` composable requests permission, subscribes, sends subscription to API
- Backend: store subscription, use `web-push` PHP lib to send push notifications
- Migration: `push_subscriptions` table (user_id, endpoint, keys, timestamps)

**Step 4: Install web-push**

```bash
composer require minishlink/web-push
```

**Step 5: Wire push into NotificationService**

Add push channel type that sends via stored subscriptions.

**Step 6: Test on mobile**

Install PWA, verify push notifications arrive.

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(pwa): add PWA manifest, service worker, push notifications"
```

---

## Phase 9: Team Dashboard & Settings

### Task 16: Team dashboard (global metrics)

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `resources/js/Pages/Dashboard.vue`
- Create: `app/Services/MetricsService.php`

**Step 1: Delegate to GLM — MetricsService**

Queries for: total checks today/month, avg uptime %, avg response time, active incidents, monitors by status. Uses efficient aggregation queries.

**Step 2: Delegate to GLM — DashboardController**

Passes metrics to Inertia.

**Step 3: Delegate to GLM — Dashboard.vue**

Cards with glassmorphism: total monitors, avg uptime (big %), avg response time, active incidents. Sparkline charts for trends. Quick list of down monitors if any.

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(dashboard): add team dashboard with global metrics"
```

---

### Task 17: Team settings page

**Files:**
- Create: `app/Http/Controllers/TeamSettingsController.php`
- Create: `resources/js/Pages/Settings/Index.vue`
- Create: `resources/js/Pages/Settings/Members.vue`
- Create: `resources/js/Pages/Settings/ApiTokens.vue`

**Step 1: Delegate to GLM — controllers + pages**

Team name edit, invite members (by email), manage API tokens (create/revoke).

**Step 2: Commit**

```bash
git add -A && git commit -m "feat(settings): add team settings, members, API tokens pages"
```

---

## Phase 10: Bonus Features

### Task 18: Response time alerting

**Files:**
- Create: `database/migrations/xxxx_add_threshold_columns_to_monitors.php`
- Modify: `app/Services/CheckService.php`
- Modify: `resources/js/Pages/Monitors/Show.vue` (threshold zones on chart)
- Modify: `resources/js/Pages/Monitors/Create.vue` (threshold fields)

**Step 1: Delegate to GLM — migration**

Add `warning_threshold_ms` and `critical_threshold_ms` (nullable integers) to monitors.

**Step 2: Delegate to GLM — threshold logic in CheckService**

After check, if response_time > critical_threshold for 3 consecutive checks → alert. Same for warning. New notification type: `monitor.slow`.

**Step 3: Delegate to GLM — UI changes**

Add threshold inputs to create/edit forms. Show colored zones on response time chart.

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(monitoring): add response time threshold alerting"
```

---

### Task 19: Badge SVG embeddable

**Files:**
- Create: `app/Http/Controllers/BadgeController.php`
- Modify: `routes/web.php`

**Step 1: Delegate to GLM — BadgeController**

Public endpoint `GET /badge/{monitor:hashid}.svg`. Returns dynamic SVG (no auth). Shows "up 99.9%" in green or "down" in red. Uses shields.io-style badge format. Cache response for 60 seconds.

Hashid: use `hashids/hashids` package to encode monitor ID.

**Step 2: Install hashids**

```bash
composer require hashids/hashids
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(badge): add embeddable uptime SVG badge"
```

---

### Task 20: Latency heatmap

**Files:**
- Create: `resources/js/Components/LatencyHeatmap.vue`
- Modify: `app/Http/Controllers/MonitorController.php` (add heatmap data)
- Modify: `resources/js/Pages/Monitors/Show.vue`

**Step 1: Delegate to GLM — LatencyHeatmap.vue**

GitHub-style contribution grid. 52 weeks x 7 days. Color scale: green (fast) → yellow → red (slow). Tooltip on hover showing date + avg response time. Pure CSS + Vue, no heavy lib.

**Step 2: Delegate to GLM — backend aggregation query**

Group monitor_checks by day, calculate avg response_time_ms per day for last 12 months.

**Step 3: Wire into Show.vue**

Add heatmap below response time chart.

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(ui): add GitHub-style latency heatmap on monitor detail"
```

---

### Task 21: Lighthouse score (daily)

**Files:**
- Create: `database/migrations/xxxx_create_monitor_lighthouse_scores_table.php`
- Create: `app/Models/MonitorLighthouseScore.php`
- Create: `app/Jobs/RunLighthouseAudit.php`
- Create: `app/Services/LighthouseService.php`
- Create: `resources/js/Components/LighthouseScores.vue`

**Step 1: Delegate to GLM — migration + model**

`monitor_lighthouse_scores`: id, monitor_id, performance, accessibility, best_practices, seo (all integer 0-100), scored_at.

**Step 2: Delegate to GLM — LighthouseService**

Uses Chrome headless + Lighthouse CLI (installed in Docker). Runs audit, parses JSON output, returns scores.

**Step 3: Delegate to GLM — RunLighthouseAudit job**

Scheduled daily. Runs for each active monitor. Stores scores.

**Step 4: Delegate to GLM — LighthouseScores.vue**

4 circular progress indicators (performance, accessibility, best practices, SEO) with score inside. Color coded: green >89, orange 50-89, red <50. Historical sparkline below.

**Step 5: Add to scheduler**

```php
Schedule::job(new RunLighthouseAudit)->dailyAt('03:00');
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(lighthouse): add daily Lighthouse audit with score display"
```

---

## Phase 11: CLI Companion

### Task 22: up-cli NPM package

**Files:**
- Create: `cli/package.json`
- Create: `cli/bin/up.js`
- Create: `cli/src/commands/add.js`
- Create: `cli/src/commands/list.js`
- Create: `cli/src/commands/rm.js`
- Create: `cli/src/commands/status.js`
- Create: `cli/src/commands/pause.js`
- Create: `cli/src/commands/login.js`
- Create: `cli/src/api.js`
- Create: `cli/README.md`

**Step 1: Delegate to GLM — full CLI package**

Uses `commander` for CLI framework, `axios` for API calls, `conf` for storing API token locally. Commands:
- `up login <token>` — store API token
- `up add <url> [--name "My Site"] [--interval 1]` — create monitor
- `up list` — table of all monitors with status
- `up status [id]` — detailed status of one or all monitors
- `up rm <id>` — delete monitor
- `up pause <id>` / `up resume <id>`

**Step 2: Test locally**

```bash
cd cli && npm link && up login <test-token> && up list
```

**Step 3: Commit**

```bash
git add cli/ && git commit -m "feat(cli): add up-cli companion NPM package"
```

---

## Phase 12: Polish & Documentation

### Task 23: README + Documentation

**Files:**
- Create: `README.md`
- Create: `docs/api.md`
- Create: `.env.example`

**Step 1: Delegate to GLM — README.md**

Professional open-source README: logo, badges, screenshots section, features list, quick start (Docker), configuration, API docs link, CLI usage, contributing guide, license (MIT).

**Step 2: Delegate to GLM — API documentation**

Full API reference with curl examples for every endpoint.

**Step 3: Delegate to GLM — .env.example**

All environment variables with comments.

**Step 4: Commit**

```bash
git add -A && git commit -m "docs: add README, API documentation, .env.example"
```

---

### Task 24: Final polish

**Step 1: Run full test suite**

```bash
php artisan test
```

**Step 2: Run linting**

```bash
./vendor/bin/pint
npm run lint
```

**Step 3: Docker compose full test**

```bash
docker compose up -d
# Verify all services healthy
docker compose ps
```

**Step 4: Final commit + tag**

```bash
git add -A && git commit -m "chore: final polish and linting"
git tag v0.1.0
```

---

## Task Dependency Graph

```
Phase 1: [T1] → [T2] → [T3]
Phase 2: [T3] → [T4] → [T5]
Phase 3: [T5] → [T6] → [T7] → [T8]
Phase 4: [T6] → [T9]
Phase 5: [T6,T9] → [T10] → [T11] → [T12]
Phase 6: [T6] → [T13]
Phase 7: [T10] → [T14]
Phase 8: [T3] → [T15]
Phase 9: [T10] → [T16] → [T17]
Phase 10: [T7] → [T18], [T10] → [T19], [T10] → [T20], [T6] → [T21]
Phase 11: [T14] → [T22]
Phase 12: [T22] → [T23] → [T24]
```

## Parallelizable Tasks (can run concurrently via subagents)

- T9 (notifications) + T13 (status pages) — independent after T6
- T15 (PWA) — independent after T3
- T18 + T19 + T20 + T21 — all independent bonus features after their deps
- T22 (CLI) — independent after T14
