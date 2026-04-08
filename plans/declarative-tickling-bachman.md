# Plan: Fix Radiank monitoring issues (2026-03-26)

## Context

Le `/monitor radiank` du 26/03/2026 a révélé plusieurs problèmes critiques. L'utilisateur veut tout corriger SAUF tonnaire.fr et la sécurité WP (wp-login).

---

## Fix 1 — P0: Topelio `/top` 502 (13/14 locales)

**Root cause:** Cache pollution cross-locale dans `PriceMonitoringService`. La méthode `getAggregatedPriceDataForProducts()` n'inclut pas la locale dans la clé de cache. UK fonctionne car il a été caché en premier.

**Fichiers à modifier (projet Topela):**

| # | Fichier | Changement |
|---|---------|------------|
| 1 | `app/Services/Pricing/PriceMonitoringService.php` | Ajouter `string $locale` param à `getAggregatedPriceDataForProducts()`, inclure locale dans cache key |
| 2 | `app/Services/Pricing/PriceService.php` | Propager le param `$locale` dans `getAggregatedDataForProducts()` |
| 3 | `app/Http/Controllers/TopListController.php` | Passer `$locale` au service + l'inclure dans la cache key L287 |

**Pattern existant correct à suivre** (même fichier):
- `getRecentPriceDrops(string $locale, ...)` → cache key `"price_drops_{$locale}_{$limit}"`
- `TrustBadgeService` → `"trust_badges:{$product->id}:{$locale}"`

**Post-fix:** Purger le cache Redis (`php artisan cache:clear` ou `Redis::flushdb()` sur le serveur Hetzner)

**Déploiement:** commit + push sur master → auto-deploy Dokploy

---

## Fix 2 — P2: YAML uptime API endpoint 404

**Root cause:** Le skill `/monitor` appelle `/api/status-page/{slug}` mais la route est `/api/status-pages/public/{slug}`.

**Fichier:** `~/.claude/sites/radiank.yml` — pas de changement nécessaire. Le fix est dans le skill `/monitor` lui-même qui doit utiliser le bon endpoint. Mais vu que le skill est un template générique, le plus simple est d'adapter la config YAML pour documenter le bon pattern d'API.

**Alternative:** Aucun changement de code requis. Le status page slug "radiank" existe et fonctionne via le bon endpoint. Le skill monitor doit être mis à jour séparément.

---

## Fix 3 — P1: Dokploy API Unauthorized

**Root cause:** Le MCP server dokploy utilise `npx` — le token est probablement dans les arguments ou variables d'env.

**Action:** Vérifier `~/.claude.json` pour le token Dokploy, le renouveler si nécessaire via le dashboard Dokploy.

---

## Fix 4 — P2: blague-humour.com CF cache BYPASS

**Root cause:** Cloudflare ne cache pas le contenu. Probablement un header `Cache-Control: no-cache` envoyé par l'app.

**Action:** Ajouter une page rule Cloudflare `blague-humour.com/*` → Cache Level: Standard, Edge TTL: 1 day. Ou vérifier les headers envoyés par l'app.

---

## Fix 5 — P2: auxjardinspotagers.fr TTFB 1.05s

**Action:** Investigation serveur — vérifier si le container Docker est surchargé, si le site a un problème de build.

---

## Fix 6 — P2: juracamping.fr pas d'accès GSC

**Action:** Ajouter la vérification DNS dans Google Search Console pour juracamping.fr.

---

## Ordre d'exécution

1. **Fix 1** (Topela /top 502) — priorité absolue, impact direct sur le site principal
2. **Fix 3** (Dokploy auth) — nécessaire pour monitorer l'infra
3. **Fix 4** (blague CF) — rapide via Cloudflare
4. **Fix 2** (YAML endpoint) — documentation
5. **Fix 5-6** — investigation/manuel

## Vérification

Après Fix 1:
- `curl -s -o /dev/null -w "%{http_code}" https://fr.topelio.com/top` → 200
- `curl -s -o /dev/null -w "%{http_code}" https://us.topelio.com/top` → 200
- `curl -s -o /dev/null -w "%{http_code}" https://au.topelio.com/` → 200
- Vérifier les 14 locales /top retournent 200
