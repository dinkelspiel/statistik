<?php

use App\Jobs\SyncScbStatistics;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncScbStatistics)
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();
