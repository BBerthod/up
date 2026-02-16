<?php

use App\Jobs\DispatchChecks;
use App\Jobs\DispatchLighthouseAudits;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchChecks)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new DispatchLighthouseAudits)->everySixHours()->withoutOverlapping()->onOneServer();
