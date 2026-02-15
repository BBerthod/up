<?php

use App\Jobs\DispatchChecks;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchChecks)->everyMinute()->withoutOverlapping()->onOneServer();
