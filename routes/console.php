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

// Same reasoning, applied to WhatsApp call history, stale/idle group data,
// and the message log — see app/Console/Commands/PruneWhatsappCallLogs.php,
// PruneWhatsappGroups.php, and PruneWhatsappMessages.php.
Schedule::command('app:prune-whatsapp-call-logs')->daily();
Schedule::command('app:prune-whatsapp-groups')->daily();
Schedule::command('app:prune-whatsapp-messages')->daily();

// Catches WhatsApp bridge crashes/restarts that never fired a disconnect
// webhook — see app/Console/Commands/SyncWhatsappChannelStatuses.php for why.
Schedule::command('app:sync-whatsapp-channel-statuses')->everyFiveMinutes();

// Spins off the next run for "Enable Recurring Schedule" bulk campaigns —
// see app/Console/Commands/ProcessRecurringWhatsappCampaigns.php.
Schedule::command('app:process-recurring-whatsapp-campaigns')->everyFifteenMinutes();
