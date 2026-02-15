# MEMORY.md - Architecture Decisions & Conventions

## Architecture Decisions

### Auth System (2026-02-15)
- **Decision**: Closed registration model. Only admins create accounts.
- **Reason**: Self-hosted monitoring tool — no public signup needed.
- **Implementation**: Removed `/register` routes, added `is_admin` column, AdminSeeder reads from `.env`.
- **OAuth**: Google & GitHub login for convenience only. No account creation via OAuth.

### Admin System (2026-02-15)
- **Decision**: Simple `is_admin` boolean on users table (no roles/permissions package).
- **Reason**: Only need admin/member distinction. YAGNI — no complex RBAC needed yet.
- **Middleware**: `IsAdmin` registered as `admin` alias in `bootstrap/app.php`.

### Push Notifications (2026-02-15)
- **Decision**: Use `minishlink/web-push` directly instead of a Laravel notification channel package.
- **Reason**: Simpler integration with existing `SendNotification` job pattern. Only need to send to PushSubscriptions linked to team users.
- **Expired subscriptions**: Auto-deleted on 410 Gone response.

## Conventions Discovered

### CSS Variables (TailwindCSS 4)
- All colors defined in `resources/css/app.css` under `@theme` block
- Use `var(--color-*)` in inline styles, not hex codes
- `--color-muted: #64748b` for paused/inactive states

### Glassmorphism Classes
- `.glass` — standard panel (5% white bg, blur, border)
- `.glass-hover` — adds hover state
- `.glass-intense` — stronger glass for forms/modals
- `.form-input` — styled input fields

### Inertia Shared Data
- `auth.user.is_admin` shared via `HandleInertiaRequests`

## Known Issues

### Push notifications not fully testable locally
- **Location**: `app/Jobs/SendNotification.php:sendPush()`
- **Severity**: Low
- **Detail**: VAPID keys must be generated and configured. No automated test for actual push delivery.

### Socialite requires packages not yet installed
- **Location**: `composer.json`
- **Severity**: High (blocks OAuth)
- **Detail**: `laravel/socialite` must be installed via `composer require laravel/socialite`. Also `minishlink/web-push` for push notifications.
- **Discovered**: 2026-02-15
