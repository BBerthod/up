# Plan d'audit swarm — Up (uptime monitoring)

## Contexte

L'application **Up** (Laravel 12 + Vue 3 + Inertia, multi-tenant, WebSocket Reverb) a été développée de manière itérative : plusieurs systèmes (Lighthouse, warming, notifications, circuit breaker) ont été ajoutés par couches successives. Les commits récents laissent transparaître des corrections rétroactives (spam de notifications, queue worker Docker oublié, jobs Lighthouse mal isolés).

**Objectif** : lancer un swarm d'agents spécialisés en parallèle pour produire un **rapport d'audit exhaustif** qui identifie :
- Problèmes de **conception** (couplage excessif, responsabilités mal réparties, séparation of concerns)
- **Oublis** structurels (indexes, retry policies, cleanup, observability)
- **Systèmes mal conçus** (race conditions, rate limiting fragile, multi-tenant incomplet)
- **Dette technique** cachée (controllers obèses, pages Vue monolithiques, tests absents)

**Livrable** : rapport unique `plans/audit-2026-04-23.md` avec findings scorés (Critique / Haute / Moyenne / Basse), fichiers/lignes, effort estimé, et roadmap priorisée.

## Pré-indicateurs déjà relevés

Exploration rapide (Phase 1) a déjà sorti ces signaux — ils serviront de **hypothèses à valider/infirmer** par le swarm, pas de conclusions :

| Domaine | Signal |
|---|---|
| Jobs | `DispatchFunctionalChecks`, `PruneWarmRuns`, `DispatchChecks` sans `$tries`/`$backoff`/`failed()` |
| Locks | `Cache::lock()` avec TTL par défaut ≈ 24h dans `CheckService` et `RunWarmSite` |
| Controllers | `MonitorController::show()` (~200 lignes, 12+ queries), `IncidentController::index()` sans scope team explicite |
| Multi-tenant | `team_id` manque d'indexes sur tables critiques (monitors, status_pages, notification_channels, warm_sites) |
| Security | Form Requests avec `authorize() => true`, throttle faible sur `/ingest/{token}` |
| Frontend | `Dashboard.vue` (632 lignes), `Monitors/Show.vue` (569 lignes), `router.reload()` full-page sur chaque event temps-réel |
| Tests | `.github/workflows/` absent, pas de tests pour Checkers / Services / Policies |
| Checkers | `PingChecker` via `exec()` sans timeout OS, `DnsChecker` sans timeout, SSL parsing silencieux |
| i18n | Zéro internationalisation frontend |
| Observability | Pas d'APM, pas de métriques, pas de dashboard interne des jobs stuck |

## Architecture du swarm (8 agents parallèles)

Les agents tournent **en parallèle** via `Agent` tool (single message, multiple tool calls). Chaque agent a un domaine **exclusif** pour éviter les doublons. Chacun reçoit les pré-indicateurs ci-dessus comme **contexte de départ**.

| # | Agent | Domaine | Focus |
|---|---|---|---|
| 1 | `laravel-expert` | **Services & domaine métier** | Couplage, séparation controllers/services/jobs, DTOs, enums, duplication logique. Valider l'emplacement de la logique dans `MonitorController::show`, `IncidentController::index`. |
| 2 | `database-expert` | **Schema & performance DB** | Indexes manquants (notamment `team_id`), migrations rollback-safe, FK + ON DELETE, N+1 confirmés, subselects coûteux (`uptime_24h`), taille des tables historiques (checks, lighthouse_scores). |
| 3 | `security-reviewer` | **Sécurité** (OWASP) | SSRF checkers (IPs internes, localhost, cloud metadata 169.254.169.254), team scope bypass, Sanctum scopes, webhooks signature, CSRF sur status pages publiques, secrets/PII dans code, rate limiting. |
| 4 | `laravel-expert` (instance #2) | **Jobs, queues, scheduler** | Retry/backoff/failed, idempotence, race conditions, `Cache::lock` TTL, `withoutOverlapping`, stuck job detection, deadletter, queue isolation (Lighthouse déjà isolé, autres ?). |
| 5 | `api-expert` | **Intégrations externes & notifications** | Circuit breaker coverage (PSI, Slack, Discord, Telegram, Webhook, SMTP, Push), retry exponentiel, deduplication multi-channel, quotas API (PSI 400/day/key), timeout HTTP par défaut, gestion des 429. |
| 6 | `frontend-expert` | **Frontend, UX, real-time** | Découpage composants (`Dashboard.vue`, `Monitors/Show.vue`), `useRealtimeUpdates` full-reload vs patch, props drilling, a11y (ARIA, focus trap, keyboard), dark/light mode regressions, code splitting, i18n. |
| 7 | `testing-expert` | **Couverture de tests** | Inventaire `tests/`, ratio Feature/Unit, scénarios critiques manquants (notification spam, circuit breaker transitions, multi-tenant isolation, SSRF guards), setup CI manquant. |
| 8 | `devops-expert` | **Infrastructure & observability** | `docker-compose.yml` (healthchecks, limits, OOM), supervisor config, scheduler onOneServer pertinent (single node ?), logs structurés, APM, metrics, CI/CD (`.github/workflows` absent), backups, secret management. |

### Fichiers critiques à charger dans chaque prompt d'agent

Pour éviter que les agents repartent de zéro, chaque prompt inclura les chemins pertinents :

- Services : `app/Services/{CheckService,NotificationService,MetricsService,WarmingService,LighthouseService}.php`
- Checkers : `app/Services/Checkers/*.php`
- Jobs : `app/Jobs/*.php`
- Controllers : `app/Http/Controllers/{MonitorController,IncidentController,StatusPageController,NotificationChannelController}.php`
- Models : `app/Models/*.php`
- Policies : `app/Policies/*.php`
- Migrations : `database/migrations/*.php`
- Frontend clés : `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Monitors/Show.vue`, `resources/js/composables/useRealtimeUpdates.ts`
- Infra : `docker-compose.yml`, `Dockerfile`, `docker/supervisor/*`, `routes/console.php`

### Format de rapport imposé à chaque agent

Chaque agent retourne un **markdown structuré** (< 800 mots) :

```markdown
## [Domaine] — findings

### CRITIQUE — <titre court>
- **Fichier** : path:line
- **Problème** : description factuelle (pas de jugement)
- **Impact** : conséquence concrète
- **Effort** : Quick fix / Half day / Multi-day
- **Fix suggéré** : 1-2 lignes

(idem pour HAUTE, MOYENNE, BASSE)

### Points positifs
- 2-3 bullets (éviter le biais négatif pur)
```

Scoring :
- **CRITIQUE** : faille sécurité exploitable, data loss, downtime, data leak multi-tenant
- **HAUTE** : dégradation utilisateur visible, flakiness, perf x10
- **MOYENNE** : dette technique, maintenance difficile, oubli structurant
- **BASSE** : qualité, cohérence, cosmétique

## Phase de synthèse (Opus orchestrateur)

Après retour des 8 agents, Opus :

1. **Déduplique** les findings (plusieurs agents peuvent relever le même problème sous angles différents).
2. **Réconcilie** les contradictions éventuelles.
3. **Priorise** en matrice Impact × Effort (quick wins d'abord).
4. **Rédige** `plans/audit-2026-04-23.md` structuré :
   - Executive summary (10 lignes)
   - Top 10 findings CRITIQUES/HAUTES
   - Tableau complet par domaine
   - Roadmap suggérée (semaine 1 / mois 1 / trimestre)
   - Annexe : findings MOYENNE/BASSE
5. **Stocke** les décisions architecturales dans vector memory (`mcp__vector-memory-radiank__remember`) pour futures sessions.

## Garde-fous

- **Mode read-only** : aucun agent n'a le droit de modifier du code pendant l'audit. Seulement Read/Grep/Glob/Bash (read-only).
- **Pas de faux positifs** : chaque finding doit citer un fichier:ligne précis et un impact concret. Un "ce serait mieux si" sans preuve → rejeté.
- **Pas d'over-engineering** : l'audit ne propose pas de refactors massifs. Les fixes suggérés sont les plus petits possibles qui règlent le problème.
- **Validation inter-agents** : si deux agents contredisent, Opus lit le code lui-même avant de trancher.

## Vérification end-to-end

1. Lancer les 8 agents en parallèle (1 message, 8 tool calls).
2. Vérifier que chaque rapport cite au moins 5 findings avec fichier:ligne.
3. Comparer les pré-indicateurs ci-dessus avec les résultats : tout signal initial doit être soit confirmé, soit explicitement invalidé.
4. Produire le rapport final `plans/audit-2026-04-23.md`.
5. Commit du rapport sur branche dédiée `audit/2026-04-23` (pas sur main).
6. Offrir à l'utilisateur de transformer les findings CRITIQUE/HAUTE en issues GitHub (optionnel, confirmer avant).

## Temps estimé

- Exploration + swarm : 8 agents × ~4 min = 8-10 min (parallèle)
- Synthèse Opus : 10-15 min
- **Total** : ~20-25 min pour livrable complet.
