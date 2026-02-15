# Design: New Monitor Types + Advanced Incidents

**Date**: 2026-02-15
**Status**: Approved
**Branch**: `feature/monitor-types-incidents`

## Overview

Extend Up with 3 new monitor types (Ping, Port TCP, DNS Records) and add a global incidents page with filtering, sorting, and export capabilities.

---

## Part 1: New Monitor Types

### 1.1 MonitorType Enum

New enum `app/Enums/MonitorType.php`:
- `HTTP` (default, existing behavior)
- `PING` (ICMP)
- `PORT` (TCP connection)
- `DNS` (DNS record validation)

### 1.2 Migration

Add to `monitors` table:
- `type` enum (default: 'http')
- `port` smallint, nullable (for PORT type, range 1-65535)
- `dns_record_type` string, nullable (A, AAAA, CNAME, MX, TXT, NS, SOA, SRV)
- `dns_expected_value` string, nullable

### 1.3 Strategy Pattern - Checkers

Refactor `CheckService` to use a strategy pattern:

**Interface**: `app/Contracts/MonitorChecker.php`
```php
interface MonitorChecker
{
    public function check(Monitor $monitor): CheckResult;
}
```

**CheckResult DTO**: `app/DTOs/CheckResult.php`
```php
class CheckResult
{
    public function __construct(
        public CheckStatus $status,
        public ?int $responseTimeMs = null,
        public ?int $statusCode = null,
        public ?Carbon $sslExpiresAt = null,
        public ?string $errorMessage = null,
    ) {}
}
```

**Checkers**: `app/Services/Checkers/`
- `HttpChecker.php` - Extract existing logic from CheckService
- `PingChecker.php` - ICMP ping
- `PortChecker.php` - TCP socket connection
- `DnsChecker.php` - DNS record query + validation

**CheckService** becomes an orchestrator:
```php
public function check(Monitor $monitor): MonitorCheck
{
    $checker = $this->resolveChecker($monitor->type);
    $result = $checker->check($monitor);
    return $this->processResult($monitor, $result);
}
```

### 1.4 PingChecker

- Uses `exec('ping -c 3 -W 5 ' . escapeshellarg($host))`
- Response time = average of successful pings (ms)
- Status UP = at least 1 response out of 3
- Status DOWN = 0 responses
- Security: strict hostname validation + escapeshellarg()

### 1.5 PortChecker

- Uses `stream_socket_client("tcp://{host}:{port}", ...)` native PHP
- Response time = TCP connection time
- Status UP = connection successful
- Status DOWN = connection refused/timeout
- Default timeout: 10 seconds
- Suggested ports in UI: HTTP(80), HTTPS(443), SSH(22), FTP(21), SMTP(25/587), MySQL(3306), PostgreSQL(5432), Redis(6379)

### 1.6 DnsChecker

- Uses `dns_get_record()` native PHP
- Supported types: A, AAAA, CNAME, MX, TXT, NS, SOA, SRV
- Status UP = returned value matches `dns_expected_value`
- Status DOWN = different value or no record found
- Response time = DNS query time
- One monitor per record (can create multiple for same domain)

### 1.7 Validation (Form Requests)

`MonitorRequest` updated with conditional rules per type:
- HTTP: url required (valid URL), method required, expected_status_code
- PING: url required (hostname/IP only)
- PORT: url required (hostname/IP), port required (1-65535)
- DNS: url required (domain), dns_record_type required, dns_expected_value required

### 1.8 UI: Create/Edit Monitor

Dynamic form fields based on selected `type`:
- Type selector (dropdown/radio at top)
- Show/hide fields based on type
- Port: suggest presets (dropdown with common ports + custom)
- DNS: record type dropdown + expected value input

---

## Part 2: Advanced Incidents

### 2.1 IncidentController

New controller: `app/Http/Controllers/IncidentController.php`

**Route**: `GET /incidents` (Inertia page)

**Filters** (query params):
- `status`: `active` | `resolved` | `all` (default: all)
- `cause`: IncidentCause enum value
- `monitor_id`: filter by specific monitor
- `from` / `to`: date range on started_at
- `duration_min` / `duration_max`: filter by duration (minutes)

**Sorting** (sortable columns):
- `started_at` (default, desc)
- `resolved_at`
- `duration` (computed)
- `monitor_name` (via join)

**Pagination**: 25 per page

### 2.2 Frontend: Incidents/Index.vue

Layout:
- Header "Incidents" with active count badge (red if > 0)
- Horizontal filter bar: status dropdown, cause dropdown, monitor select, date range picker
- Sortable table columns
- Export button (top right)
- Pagination at bottom

Table columns:
- Status icon (green = resolved, red = active)
- Monitor name (link to monitor show page) + type badge
- Root cause (badge)
- Started at (datetime, formatted)
- Resolved at (datetime or "Ongoing")
- Duration (human readable: "2h 15m 30s")

### 2.3 Export Endpoint

**Route**: `GET /incidents/export?format=csv|json`

Same filters as the incidents page. Streamed response.

- CSV columns: Monitor, Type, Status, Cause, Started, Resolved, Duration (seconds)
- JSON: array of objects with same fields
- Max 10,000 incidents per export
- Content-Disposition header for file download

---

## Implementation Plan (Atomic Commits)

### Step 1: Migration + MonitorType Enum
- Create `MonitorType` enum
- Migration: add `type`, `port`, `dns_record_type`, `dns_expected_value`
- Update Monitor model ($casts, $fillable)
- **Commit**: `feat(monitors): add monitor type enum and migration`

### Step 2: Strategy Pattern + CheckResult DTO
- Create `MonitorChecker` interface
- Create `CheckResult` DTO
- Refactor `CheckService` as orchestrator
- Extract existing logic into `HttpChecker`
- Tests for HttpChecker (ensure no regression)
- **Commit**: `refactor(checks): extract strategy pattern with HttpChecker`

### Step 3: PingChecker
- Implement `PingChecker`
- Unit tests
- **Commit**: `feat(monitors): add ping (ICMP) checker`

### Step 4: PortChecker
- Implement `PortChecker`
- Unit tests
- **Commit**: `feat(monitors): add TCP port checker`

### Step 5: DnsChecker
- Implement `DnsChecker`
- Unit tests
- **Commit**: `feat(monitors): add DNS record checker`

### Step 6: Validation + API
- Update `MonitorRequest` with type-conditional rules
- Update `MonitorController` and `MonitorApiController`
- Tests for validation rules per type
- **Commit**: `feat(monitors): add type-aware validation and API support`

### Step 7: UI Create/Edit
- Update `Create.vue` and `Edit.vue` with dynamic type fields
- Port presets, DNS record type dropdown
- **Commit**: `feat(monitors): add dynamic type fields in create/edit UI`

### Step 8: Incidents Page
- Create `IncidentController`
- Add route
- Create `Incidents/Index.vue` with filters, sorting, pagination
- **Commit**: `feat(incidents): add global incidents page with filters`

### Step 9: Incidents Export
- Add export endpoint to `IncidentController`
- CSV + JSON streaming
- **Commit**: `feat(incidents): add CSV/JSON incident export`

---

## Delegation Strategy

| Step | Who | Why |
|------|-----|-----|
| 1. Migration + Enum | GLM | Boilerplate generation |
| 2. Strategy pattern | GLM (thinking: on) | Refactoring with context |
| 3-5. Checkers | GLM | Code generation |
| 6. Validation | GLM | Form request rules |
| 7. UI Create/Edit | GLM | Vue component generation |
| 8. Incidents page | GLM (thinking: on) | Backend + frontend |
| 9. Export | GLM | Streaming response |
| Review & Integration | Opus | Quality validation |
