# Fix Radiank Monitoring Issues

## Context
SEO monitoring run on 2026-03-27 revealed 5 critical issues across Radiank sites requiring immediate action.

## Fixes

### Fix 1: wekompare.fr — Regenerate stale sitemaps
- **Problem**: Sitemaps last updated Aug 2025, old URL structure → 301 redirects → Google marks as "detected not indexed" (13,085 pages)
- **Action**: Run WP-CLI `wp yoast index --reindex` or equivalent sitemap regeneration via SSH/WP admin on the wekomparefr container
- **Codebase**: `~/Dev/wekomparefr/`
- **Verification**: `curl -s https://wekompare.fr/sitemap_index.xml` should show fresh `<lastmod>` dates

### Fix 2: topelio — Investigate infrastructure 502s
- **Problem**: Health API, 8/14 locale sitemaps, and au.topelio.com all return 502. Code/config is correct — infra issue (DB/Redis/Queue)
- **Action**: Navigate to Dokploy dashboard (tab 1680494112 already open) → check topelio app logs, container status, recent deployments
- **Codebase**: `~/Dev/Topela/` (code is fine, issue is server-side)
- **Verification**: `curl -s https://fr.topelio.com/api/health` should return JSON with healthy status

### Fix 3: blague-humour + enigmes-devinettes — Remove Ezoic noindex
- **Problem**: Ezoic DNS proxy injects `meta robots: none` — entire sites deindexed from Google
- **Action**: Navigate to Ezoic dashboard via Chrome → find robots/indexation settings → disable noindex injection
- **Ezoic Site ID**: 245916 (blague-humour)
- **Verification**: `curl -s https://blague-humour.com/ | grep 'robots'` should NOT show `none`

### Fix 4: wekompare.com — Fix 404 tracking
- **Problem**: GA4 #1 page = "404 - Page not found" (260 views). `/sitemap.xml` redirects to `/sitemaps.xml`
- **Action**: Add nginx rewrite rule for `/sitemap.xml` → `/sitemaps.xml` in wekomparecom Docker config, or check WordPress permalink flush
- **Codebase**: `~/Dev/wekomparecom/`
- **Verification**: GA4 404 page views should decrease over next week

### Fix 6: topelio robots.txt
- **Problem**: curl returns 0 Sitemap directives. robots.txt is dynamically generated but server returns 502
- **Action**: Same root cause as Fix 2 — once infra is healthy, robots.txt will work again
- **Verification**: `curl -s https://fr.topelio.com/robots.txt | grep Sitemap` should show 28+ directives

## Execution Order
1. Fix 3 (Ezoic) — browser action, immediate impact
2. Fix 2+6 (Topelio infra) — Dokploy investigation
3. Fix 1 (wekompare.fr sitemaps) — WP-CLI via SSH
4. Fix 4 (wekompare.com 404) — nginx config or WP permalink flush
