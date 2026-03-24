# Plan: Fix Radiank Monitoring Issues

## Context
Le monitoring `/monitor radiank` du 2026-03-23 a révélé 13 issues sur l'organisation Radiank. Ce plan couvre les fixes réalisables.

## Issues & Actions

### 1. CRITICAL — Topelio `/categories` affiche du JSON brut
**Cause**: `CategoryController::index()` (ligne 57) fait `if ($request->wantsJson() || $request->ajax())` AVANT le rendu Blade. Quand un navigateur envoie `Accept: */*` ou que le CF cache sert une réponse JSON cachée, la branche JSON est exécutée.
**Fix**: Ajouter un check explicite `$request->header('X-Requested-With') === 'XMLHttpRequest'` au lieu de `$request->ajax()`, et supprimer `$request->wantsJson()` du check (trop permissif). Ou mieux: utiliser un préfixe `/api/categories` dédié.
**Fichier**: `~/Dev/Topela/app/Http/Controllers/CategoryController.php:57`
**Action**: Fix dans le repo Topela, commit, push → auto-deploy.

### 2. HIGH — Topelio: pas de Docker healthcheck
**Action**: Via Dokploy MCP `application-update` sur `d9q20iDjwNGYdZQ9cMMYf`, ajouter un healthcheck.

### 3. HIGH — tonnaire.fr: pas de headers sécurité + TTFB 1.23s
**Cause**: Site compose, pas d'app Dokploy. Headers absents dans Traefik config.
**Action**: Via Dokploy MCP `deployment-allByCompose` pour identifier le compose, puis `application-readTraefikConfig` / `updateTraefikConfig`. Si impossible, skip (nécessite SSH).

### 4. HIGH — juracamping.fr: WP 6.7.2 outdated
**Action**: Via Dokploy compose, exécuter `wp core update` dans le container. Nécessite accès shell.
**Alternative**: Note pour action manuelle.

### 5. HIGH — wekompare.com: GA4 trafic ↓56%
**Action**: Diagnostic uniquement. Pas de fix technique — nécessite analyse contenu/SEO.

### 6. MEDIUM — enigmes-devinettes.com: missing HSTS
**Action**: Même approche que tonnaire — Traefik middleware ou Cloudflare config.

### 7. MEDIUM — blague-humour.com: CF BYPASS + www=404
**Action**: CF BYPASS = page rules Cloudflare à configurer (hors scope CLI). www=404 = DNS CNAME manquant.

### 8. LOW — Topelio: 15 failed jobs, scheduler behind
**Action**: Monitoring only. Amélioré vs 25 le 19/03.

## Plan d'exécution

1. **Fix Topelio /categories** (Topela repo) → branch + fix + push
2. **Add Docker healthcheck** via Dokploy MCP
3. **Documenter les issues infra** nécessitant SSH/Cloudflare
4. **Update vector-memory** avec le snapshot corrigé

## Hors scope (nécessite accès externe)
- Traefik security headers pour compose services → SSH Hetzner
- Cloudflare page rules pour CF BYPASS → Dashboard CF
- DNS www pour blague/enigmes/juracamping → Dashboard CF
- WP update juracamping → shell dans container Docker
- Analyse trafic wekompare.com → investigation séparée

## Vérification
- Après fix /categories: `curl -s https://fr.topelio.com/categories | head -1` ne doit PAS commencer par `{`
- Après healthcheck: Dokploy `application-one` montre `healthCheckSwarm` non null
