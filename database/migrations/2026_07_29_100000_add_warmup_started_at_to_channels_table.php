<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // Anchors the warm-up daily-send-cap ramp (CampaignDispatcher) — set
            // the first time any campaign with warm_up_mode dispatches on this
            // channel. Day number = calendar days elapsed since this timestamp,
            // shared across every campaign sent on the channel so the ramp
            // reflects the number's real sending history, not any one campaign.
            $table->timestamp('warmup_started_at')->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('warmup_started_at');
        });
    }
};
