<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL silently attaches `DEFAULT CURRENT_TIMESTAMP ON UPDATE
 * CURRENT_TIMESTAMP` to the first NOT NULL timestamp column in a table when
 * no explicit default is given — `started_at` had no explicit default, so
 * every update to a call log row (setting ended_at/status/reply_type, none
 * of which touch started_at) was silently overwriting it with the current
 * DB-server time. Confirmed live: `SHOW CREATE TABLE` showed the implicit
 * ON UPDATE clause; every row's started_at had drifted to "now" at last
 * update instead of holding the real call-start time. `->useCurrent()`
 * gives it an explicit default (still correct for the create-time value),
 * which stops MySQL from also attaching the implicit ON UPDATE behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_call_logs', function (Blueprint $table) {
            $table->timestamp('started_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_call_logs', function (Blueprint $table) {
            $table->timestamp('started_at')->change();
        });
    }
};
