<?php

use Illuminate\Support\Facades\Schedule;

// Automatic closing at the deadline (§3.2).
Schedule::command('job-postings:close-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
