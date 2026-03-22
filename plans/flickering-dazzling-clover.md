# Automated Weekly Uptime Reports

## Context

Up currently sends real-time alerts (down/up) but has no periodic summary reporting. Users want a weekly email digest showing uptime stats, incidents, and response times — similar to Uptime Robot and Better Stack's automated reports. This reduces notification fatigue and gives a big-picture view.

## Approach

Weekly report sent every **Monday at 8:00 AM** (per team timezone, defaulting to UTC). Each user can **opt-in/opt-out** via a toggle in Settings. Report covers the **previous 7 days**.

## Files to create

### 1. Migration: `add_weekly_report_opt_in_to_users_table`
- Add `weekly_report_enabled` BOOLEAN DEFAULT `true` to `users` table
- All existing users get `true` (opt-in by default)

### 2. `app/Services/WeeklyReportService.php`
Build report data per team, reusing patterns from `MetricsService`:
- **Overall uptime %** (7d) — reuse `AVG(CASE WHEN status='up' ...)` pattern from `MetricsService:82-87`
- **Per-monitor breakdown**: name, uptime %, avg response time, worst response time
- **Incidents summary**: count, total downtime minutes, list with cause/duration
- **Highlights**: best/worst monitor, longest incident

### 3. `app/Mail/WeeklyReportMail.php`
Laravel Mailable following `MonitorAlertMail` pattern:
- Subject: `[Up] Weekly Report — {team_name} — {date_range}`
- Content: markdown template

### 4. `resources/views/mail/weekly-report.blade.php`
Markdown email template using `<x-mail::message>` + `<x-mail::table>`:
- Header with date range + team name
- KPI row: total monitors, overall uptime %, avg response time
- Monitor table: name | uptime % | avg ms | status
- Incidents table: monitor | cause | duration | resolved?
- Footer with link to dashboard

### 5. `app/Jobs/SendWeeklyReports.php`
Queued job dispatched by scheduler:
- Iterate each team
- Build report via `WeeklyReportService`
- Send to each user where `weekly_report_enabled = true`
- Queue: `notifications`

### 6. `routes/console.php`
Add schedule entry:
```php
Schedule::job(new SendWeeklyReports)->weeklyOn(1, '08:00')->withoutOverlapping()->onOneServer();
```

### 7. `app/Models/User.php`
- Add `weekly_report_enabled` to `$fillable` and `$casts` (boolean)

### 8. `resources/js/Pages/Settings/Index.vue`
- Add toggle in the Settings page: "Receive weekly uptime report"
- POST to `/settings/weekly-report` with `{ enabled: bool }`

### 9. `app/Http/Controllers/SettingsController.php`
- Add `updateWeeklyReport(Request $request)` method
- Validate `enabled` as boolean, update `auth()->user()->weekly_report_enabled`

### 10. `routes/web.php`
- Add `POST /settings/weekly-report` route

## Files to modify

| File | Change |
|------|--------|
| `app/Models/User.php` | Add `weekly_report_enabled` to fillable/casts |
| `routes/console.php` | Add `SendWeeklyReports` schedule |
| `routes/web.php` | Add settings route |
| `resources/js/Pages/Settings/Index.vue` | Add opt-in toggle |
| `app/Http/Controllers/SettingsController.php` | Add `updateWeeklyReport` |

## Verification

1. Run migration
2. `php artisan tinker` — call `WeeklyReportService::generate(Team::first())` to verify data
3. `php artisan tinker` — trigger `SendWeeklyReports::dispatchSync()` to verify email
4. Check Mailpit (localhost:1025) for rendered email
5. Toggle opt-out in Settings UI, re-trigger, verify no email received
6. Run `vendor/bin/phpunit` and `vendor/bin/pint`
