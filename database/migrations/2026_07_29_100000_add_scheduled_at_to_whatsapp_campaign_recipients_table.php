<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            // The intended send time computed at dispatch time (vs. sent_at,
            // which is when the send actually completed). Needed so the
            // warm-up daily-send-cap ramp can count how many recipients are
            // already allocated to a given calendar day across every campaign
            // on a channel; also populated for non-warm-up dispatches.
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
