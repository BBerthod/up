# Cache Warming — Design Document

> Date: 2026-03-20
> Status: Approved

## Overview

New standalone section in Up for keeping CDN/application caches warm by periodically visiting site URLs. Complements monitoring: monitors check a site is *up*, cache warming ensures it's *fast* for real users.

## Data Model

### `warm_sites` — Site configuration

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `team_id` | FK → teams | Multi-tenant scoping |
| `name` | string | Label (e.g., "Blog principal") |
| `domain` | string | Domain (e.g., `wekompare.com`) |
| `mode` | enum | `urls` (manual list) or `sitemap` |
| `sitemap_url` | string nullable | Defaults to `https://{domain}/sitemap.xml` |
| `urls` | json nullable | Manual URL list (mode `urls`) |
| `frequency_minutes` | int | 15, 30, 60, 120, 360, 720, 1440 |
| `max_urls` | int | Limit per run (default 50, max 500) |
| `custom_headers` | json nullable | Headers to attach to requests |
| `is_active` | bool | Toggle on/off |
| `last_warmed_at` | timestamp nullable | |
| `timestamps` | | created_at, updated_at |

### `warm_runs` — Run history

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `warm_site_id` | FK → warm_sites | |
| `urls_total` | int | URLs processed |
| `urls_hit` | int | Cache HIT detected |
| `urls_miss` | int | Cache MISS (warmed) |
| `urls_error` | int | Errors (timeout, 4xx, 5xx) |
| `avg_response_ms` | int | Average response time |
| `status` | enum | `running`, `completed`, `failed` |
| `started_at` | timestamp | |
| `completed_at` | timestamp nullable | |
| `timestamps` | | created_at, updated_at |

## Backend Architecture

### Jobs

**`DispatchWarmRuns`** — Scheduled every minute
- Queries active `warm_sites` where `last_warmed_at` + `frequency_minutes` is past due
- Dispatches one `RunWarmSite` per eligible site
- Same pattern as existing `DispatchChecks`

**`RunWarmSite`** — Worker per site
1. Creates `warm_run` with status `running`
2. Resolves URL list:
   - Mode `sitemap`: fetch sitemap.xml, parse `<loc>` entries, respect `max_urls`
   - Mode `urls`: use JSON list directly
3. Visits each URL sequentially (1 req/sec default)
   - GET with configured `custom_headers`
   - Captures: HTTP status, `X-Cache` / `cf-cache-status` header, response time
4. Updates `warm_run` with aggregated stats
5. Updates `warm_site.last_warmed_at`

### Queue & Throttling

- Dedicated queue: `warming`
- 1 request/second per site (sequential within a site)
- Sites warmed in parallel (separate jobs)
- Timeout: 10s per URL, 30 min max per run

## UI Pages

### Index — `/warming`

Card/table list of configured sites:
- Name + domain
- Mode badge (`Sitemap` / `URLs`)
- Frequency label
- Last run: timestamp + hit ratio badge (green >80%, yellow >50%, red <50%)
- Active/paused toggle
- "Warm Now" button for immediate trigger

### Create/Edit — `/warming/create`, `/warming/{id}/edit`

Dynamic form:
- Name, Domain
- Mode switcher (Sitemap / URLs)
  - Sitemap → URL field (pre-filled `https://{domain}/sitemap.xml`)
  - URLs → editable textarea/list
- Frequency (select dropdown)
- Max URLs per run
- Custom Headers (key/value pairs, collapsible)

### Show — `/warming/{id}`

Site dashboard:
- Last run stats (total, hit, miss, error, avg response)
- Historical chart: hit ratio % over time
- Table of recent runs (date, duration, URLs, hit/miss/error, status)
- "Warm Now" button

## Navigation

New top-level nav item: "Cache Warming" (flame icon), same level as Monitors, Incidents, Channels, Status Pages.

## Scope Boundaries (YAGNI)

NOT included in v1:
- Multi-region warming (single origin)
- Per-URL detail in runs (aggregated stats only)
- Webhooks/notifications on run completion
- Link to existing monitors

## Research Sources

- [Oh Dear — Varnish cache warming via monitoring crawler](https://ohdear.app/news-and-updates/using-oh-dear-to-keep-your-varnish-cache-warm)
- [PageSpeedPlus — Cache Warmer with sitemap + scheduling](https://pagespeedplus.com/tools/cache-warmer)
- [cache-warmer.com — SaaS cache warming](https://www.cache-warmer.com/)
- [Laravel News — Cache Pre-warming patterns](https://laravel-news.com/cache-pre-warming-explained-laravel-in-practice-ep11)
- [Warmable Laravel package](https://github.com/henzeb/warmable-laravel)
- [OneUptime — Cache Warming Strategies](https://oneuptime.com/blog/post/2026-01-30-cache-warming-strategies/view)
