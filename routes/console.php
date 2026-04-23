<?php

use App\Jobs\DispatchChecks;
use App\Jobs\DispatchFunctionalChecks;
use App\Jobs\DispatchLighthouseAudits;
use App\Jobs\DispatchWarmRuns;
use App\Jobs\PruneFunctionalCheckResults;
use App\Jobs\PruneIngestEvents;
use App\Jobs\PruneLighthouseScores;
use App\Jobs\PruneMonitorChecks;
use App\Jobs\PruneNotificationLogs;
use App\Jobs\PruneWarmRuns;
use App\Jobs\SendWeeklyReports;
use Illuminate\Support\Facades\Schedule;

// Single-node deployment: onOneServer() removed — it requires a shared atomic cache and
// adds fragility without benefit on a single node. If a multi-node setup is ever introduced,
// re-add onOneServer() to all schedule entries that modify shared state.

Schedule::job(new DispatchChecks)->everyMinute()->withoutOverlapping();
Schedule::job(new DispatchLighthouseAudits)->everySixHours()->withoutOverlapping();
Schedule::job(new DispatchFunctionalChecks)->everyMinute()->withoutOverlapping();
Schedule::job(new PruneFunctionalCheckResults)->daily()->withoutOverlapping();
Schedule::job(new DispatchWarmRuns)->everyMinute()->withoutOverlapping();
Schedule::job(new PruneWarmRuns)->daily()->withoutOverlapping();
Schedule::job(new PruneMonitorChecks)->daily()->withoutOverlapping();
Schedule::job(new PruneNotificationLogs)->daily()->withoutOverlapping();
Schedule::job(new PruneIngestEvents)->daily()->withoutOverlapping();
Schedule::job(new PruneLighthouseScores)->daily()->withoutOverlapping();
Schedule::job(new SendWeeklyReports)->weeklyOn(1, '08:00')->withoutOverlapping();

// Prune failed_jobs older than 7 days to prevent unbounded table growth.
Schedule::command('queue:prune-failed --hours=168')->weekly()->withoutOverlapping();
