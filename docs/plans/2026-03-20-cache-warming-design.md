# Cache Warming — Design Document

> Date: 2026-03-20
> Status: Approved (v2 — post 3-pass review)

## Overview

New standalone section in Up for keeping CDN/application caches warm by periodically visiting site URLs. Complements monitoring: monitors check a site is *up*, cache warming ensures it's *fast* for real users.

## Data Model

### Enums

- `WarmSiteMode`: `urls`, `sitemap`
- `WarmRunStatus`: `running`, `completed`, `failed`

### `warm_sites` — Site configuration

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint PK | | |
| `team_id` | FK → teams | | Multi-tenant scoping (CASCADE DELETE) |
| `name` | string | | Label (e.g., "Blog principal") |
| `domain` | string | | Domain (e.g., `wekompare.com`) — unique per team |
| `mode` | WarmSiteMode | | `urls` (manual list) or `sitemap` |
| `sitemap_url` | string nullable | | Computed from domain if null |
| `urls` | json nullable | | Manual URL list (mode `urls`, max 500) |
| `frequency_minutes` | smallint | 60 | 15, 30, 60, 120, 360, 720, 1440 |
| `max_urls` | smallint | 50 | Limit per run (1–500) |
| `is_active` | bool | true | Toggle on/off |
| `last_warmed_at` | timestamp nullable | | |
| `timestamps` | | | created_at, updated_at |

**Indexes:** `(team_id, is_active)`, `(last_warmed_at)`
**Unique:** `(team_id, domain)`
**Model traits:** `BelongsToTeam`, `HasFactory`
**Scopes:** `active()`, `dueForWarming()`
**Accessor:** `sitemap_url` defaults to `https://{domain}/sitemap.xml` if null

### `warm_runs` — Run history

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `warm_site_id` | FK → warm_sites | CASCADE DELETE |
| `urls_total` | smallint | URLs processed |
| `urls_hit` | smallint | Cache HIT detected |
| `urls_miss` | smallint | Cache MISS (warmed) |
| `urls_error` | smallint | Errors (timeout, 4xx, 5xx) |
| `avg_response_ms` | smallint | Average response time |
| `status` | WarmRunStatus | `running`, `completed`, `failed` |
| `error_message` | text nullable | Summary on failure (e.g., "Sitemap parse failed") |
| `started_at` | timestamp | |
| `completed_at` | timestamp nullable | |
| `timestamps` | | created_at, updated_at |

**Indexes:** `(warm_site_id, created_at)`
**Retention:** 180 days default. Pruning job deferred to Phase 2.

### Not in v1
- ❌ `next_warmable_at` denormalized column (premature optimization at this scale)
- ❌ `custom_headers` (edge case, defer to v2)
- ❌ Per-URL detail table
- ❌ `warm_site_urls` separate table (JSON is appropriate for <500 items)

## Backend Architecture

### Service

**`WarmingService`** — follows project convention (all business logic in Services/)
- `resolveUrls(WarmSite $site): array` — sitemap parsing or manual URL list, respects max_urls
- `warmUrl(string $url): WarmUrlResult` — single HTTP GET, returns DTO with status, cache_status, response_time, error

### Jobs

**`DispatchWarmRuns`** — Scheduled every minute (`withoutOverlapping()->onOneServer()`)
- Queries `WarmSite::active()->dueForWarming()->get()`
- Dispatches one `RunWarmSite` per eligible site

**`RunWarmSite`** — Worker per site
- `$tries = 2`, `$backoff = [300, 3600]`
- Dynamic timeout via `retryUntil()`: `max_urls * 15s` (floor 2min, cap 2h)
- Acquires `Cache::lock("warming:{$warmSite->id}", 120)` — prevents duplicate runs
- Creates `warm_run` with status `running`
- Calls `WarmingService::resolveUrls()` then loops with `usleep(1_000_000)` between requests
- On 429 response: exponential backoff (5s, 15s, 30s), then skip remaining URLs
- On 3 consecutive timeouts: stop run early (site likely down)
- Updates `warm_run` with aggregated stats + `error_message` if applicable
- Implements `failed()` method with logging

### Queue & Throttling

- Queue: `default` (add `warming` dedicated queue in Phase 2 if contention observed)
- 1 request/second per site via `usleep()` between URLs
- Sites warmed in parallel (separate jobs)
- Per-URL HTTP timeout: 10s connect, 5s connect timeout

### HTTP Client Configuration

```php
Http::timeout(10)
    ->connectTimeout(5)
    ->withoutVerifying()
    ->maxRedirects(3)
    ->withHeaders([
        'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Cache-Control' => 'max-age=0',
    ])
```

### Cache Header Detection

Multi-CDN detection priority:
1. **Cloudflare:** `cf-cache-status` → HIT/MISS/EXPIRED/BYPASS/DYNAMIC
2. **Varnish:** `X-Cache` → hit/miss
3. **Nginx:** `X-Cache-Status` → HIT/MISS/EXPIRED/BYPASS
4. **CloudFront:** `X-Cache` → "Hit from cloudfront" / "Miss from cloudfront"
5. **Generic:** `Age` header > 0 → cached
6. **Default:** `unknown` if no cache headers present

### URL Handling

- **Sitemap parsing:** `SimpleXMLElement` with `LIBXML_NONET` flag (XXE protection)
- **Large sitemaps:** Shuffle URLs, pick first `max_urls` (rotating coverage across runs)
- **URL deduplication:** Normalize trailing slash, www vs non-www, lowercase domain
- **Sitemap index files:** Defer to v2 (flat sitemaps only in v1)
- **Gzipped sitemaps:** Deferred (Guzzle auto-decompresses transparently)

## Security

### SSRF Protection
- Validate all URLs (sitemap_url, urls[], parsed sitemap locs) against private IP ranges
- Reuse existing `isPrivateUrl()` from HttpChecker
- Block: `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`, `0.0.0.0/8`
- Block cloud metadata: `169.254.169.254`
- Block localhost variants: `localhost`, `::1`

### XXE Protection
- Parse sitemaps with `LIBXML_NONET` flag
- Limit sitemap response size to 5MB before parsing

### Domain Validation
- Regex for valid domain format
- Reject: private IPs, localhost, reserved TLDs (.test, .local, .example)
- Reject: ports, paths (normalize to domain only)
- Unique constraint: `(team_id, domain)`

### Per-Team Limits
- Max warm sites per team: 20
- Max URLs per run: 500 (default 50)
- Min frequency: 15 minutes

### Data Minimization
- Store only: `http_status`, `cache_status` (enum), `response_ms`
- Do NOT store raw response headers or body

## UI Pages

### Index — `/warming`

Card/table list of configured sites:
- Name + domain
- Mode badge (`Sitemap` / `URLs`)
- Frequency label (human-readable: "Every 1 hour")
- Last run: timestamp + hit ratio badge (green >80%, yellow >50%, red <50%)
- Active/paused toggle
- "Warm Now" button (POST + page refresh, no WebSocket in v1)
- Empty state when no sites configured

### Create/Edit — `/warming/create`, `/warming/{id}/edit`

Dynamic form:
- Name, Domain
- Mode switcher (Sitemap / URLs)
  - Sitemap → URL field (pre-filled `https://{domain}/sitemap.xml`)
  - URLs → textarea with one URL per line
- Frequency (SelectButton with human labels)
- Max URLs per run (number input, default 50, max 500)
- Loading/error states

### Show — `/warming/{id}`

Site dashboard:
- Header: name + domain + mode + frequency + active toggle + "Warm Now" + "Edit" + "Delete"
- Last run stats: hit %, total URLs, hit/miss/error, avg response time
- Recent runs table (last 10): date, duration, URLs, hit/miss/error, status badge
- v2: Historical hit ratio chart

### Navigation

Top-level nav item "Cache Warming" (flame icon), position after existing items:
Dashboard → Monitors → Incidents → Status Pages → Events → Sources → **Cache Warming** → Settings

## API Layer

### Routes
```
GET    /warming              → index
GET    /warming/create       → create
POST   /warming              → store
GET    /warming/{warmSite}   → show
GET    /warming/{warmSite}/edit → edit
PUT    /warming/{warmSite}   → update
DELETE /warming/{warmSite}   → destroy
POST   /warming/{warmSite}/warm-now → warmNow
```

### FormRequest Validation (Store/Update)
- `name`: required, string, max:255
- `domain`: required, valid domain regex, unique per team
- `mode`: required, WarmSiteMode enum
- `sitemap_url`: required_if:mode,sitemap, url
- `urls`: required_if:mode,urls, array, max:500
- `urls.*`: url
- `frequency_minutes`: required, in:15,30,60,120,360,720,1440
- `max_urls`: integer, min:1, max:500

### Policy
- `CacheWarmingPolicy`: view/update/delete/warmNow — all check `$user->team_id === $warmSite->team_id`

## Testing Strategy

### Feature Tests (`WarmSiteControllerTest`)
- CRUD operations, validation, team scoping
- "Warm Now" trigger, toggle active/paused
- User cannot access other team's sites

### Service Tests (`WarmingServiceTest`)
- `resolveUrls()` with mocked sitemap XML
- `warmUrl()` with various response scenarios (HIT, MISS, 429, timeout)
- URL deduplication, shuffle + limit

### Job Tests
- `DispatchWarmRunsTest`: correct sites dispatched based on frequency
- `RunWarmSiteTest`: integration with faked HTTP, warm_run record creation

### Factories
- `WarmSiteFactory` with states: `->sitemap()`, `->urls([...])`, `->inactive()`
- `WarmRunFactory` with states: `->running()`, `->failed()`

## Deployment

- Add `warming` to worker queue list: `--queue=monitors,notifications,warming,default`
- Add scheduler entry: `Schedule::job(new DispatchWarmRuns)->everyMinute()->withoutOverlapping()->onOneServer()`
- No feature flag needed (feature is inert until first site created)

## Scope Boundaries (YAGNI)

NOT in v1 (add in v2 if needed):
- Custom headers on requests
- Multi-region warming
- Per-URL detail in runs
- Webhooks/notifications on run completion
- Link to existing monitors
- Sitemap index file support
- Historical trend chart
- Real-time progress via Reverb WebSocket
- Pruning job for old warm_runs
- Tiered per-team plans

## Research Sources

- [Oh Dear — Varnish cache warming via monitoring crawler](https://ohdear.app/news-and-updates/using-oh-dear-to-keep-your-varnish-cache-warm)
- [PageSpeedPlus — Cache Warmer with sitemap + scheduling](https://pagespeedplus.com/tools/cache-warmer)
- [cache-warmer.com — SaaS cache warming](https://www.cache-warmer.com/)
- [Laravel News — Cache Pre-warming patterns](https://laravel-news.com/cache-pre-warming-explained-laravel-in-practice-ep11)
- [Warmable Laravel package](https://github.com/henzeb/warmable-laravel)
- [OneUptime — Cache Warming Strategies](https://oneuptime.com/blog/post/2026-01-30-cache-warming-strategies/view)
- [Cache Warming Architecture — AlgoMaster](https://algomaster.io/learn/system-design/cache-warming)
