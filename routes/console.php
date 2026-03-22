<?php

use App\Jobs\DispatchChecks;
use App\Jobs\DispatchFunctionalChecks;
use App\Jobs\DispatchLighthouseAudits;
use App\Jobs\DispatchWarmRuns;
use App\Jobs\PruneWarmRuns;
use App\Jobs\SendWeeklyReports;
use App\Models\FunctionalCheckResult;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchChecks)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new DispatchLighthouseAudits)->everySixHours()->withoutOverlapping()->onOneServer();
Schedule::job(new DispatchFunctionalChecks)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => FunctionalCheckResult::where('checked_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('prune-functional-check-results')
    ->withoutOverlapping();
Schedule::job(new DispatchWarmRuns)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new PruneWarmRuns)->daily()->withoutOverlapping();
Schedule::job(new SendWeeklyReports)->weeklyOn(1, '08:00')->withoutOverlapping()->onOneServer();
