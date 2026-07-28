<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the activity_logs table from growing unbounded — see
// app/Console/Commands/PruneActivityLogs.php for why.
Schedule::command('app:prune-activity-logs')->daily();
