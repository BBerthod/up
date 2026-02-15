# Up API Reference

Base URL: `http://localhost:8000/api`

All authenticated endpoints require a Bearer token:
```
Authorization: Bearer <your-api-token>
```

Create tokens in **Settings > API Tokens**.

---

## Health Check

```
GET /health
```

No authentication required.

**Response:**
```json
{ "status": "ok", "timestamp": "2026-02-15T12:00:00Z" }
```

---

## Monitors

### List Monitors

```
GET /monitors
```

### Create Monitor

```
POST /monitors
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | yes | Monitor display name |
| `url` | string | yes | URL to monitor |
| `method` | string | yes | `GET`, `POST`, or `HEAD` |
| `expected_status_code` | integer | yes | Expected HTTP status (e.g. 200) |
| `interval` | integer | yes | Check interval in minutes (1-60) |
| `keyword` | string | no | Expected keyword in response body |
| `warning_threshold_ms` | integer | no | Warning response time threshold |
| `critical_threshold_ms` | integer | no | Critical response time threshold |
| `notification_channels` | array | no | Array of notification channel IDs |

### Get Monitor

```
GET /monitors/{id}
```

### Update Monitor

```
PUT /monitors/{id}
```

### Delete Monitor

```
DELETE /monitors/{id}
```

### Pause / Resume

```
POST /monitors/{id}/pause
POST /monitors/{id}/resume
```

### Get Monitor Checks

```
GET /monitors/{id}/checks?from=2026-02-01&to=2026-02-15
```

---

## Notification Channels

### List Channels

```
GET /notification-channels
```

### Create Channel

```
POST /notification-channels
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | yes | Channel display name |
| `type` | string | yes | `email`, `webhook`, `slack`, `discord`, `push` |
| `settings` | object | yes | Type-specific settings |
| `is_active` | boolean | no | Enable/disable (default: true) |

**Settings by type:**
- Email: `{ "email": "user@example.com" }`
- Webhook: `{ "url": "https://..." }`
- Slack: `{ "webhook_url": "https://hooks.slack.com/..." }`
- Discord: `{ "webhook_url": "https://discord.com/api/webhooks/..." }`

### Update / Delete Channel

```
PUT /notification-channels/{id}
DELETE /notification-channels/{id}
```

---

## Status Pages

### CRUD

```
GET    /status-pages
POST   /status-pages
GET    /status-pages/{id}
PUT    /status-pages/{id}
DELETE /status-pages/{id}
```

### Public Status Page (No Auth)

```
GET /status-pages/public/{slug}
```

---

## Push Subscriptions

```
POST   /push-subscriptions    # Subscribe
DELETE /push-subscriptions    # Unsubscribe (body: { endpoint })
```
