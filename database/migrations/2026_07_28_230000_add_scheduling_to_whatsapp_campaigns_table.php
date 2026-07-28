<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            // "Time Post" (screenshot 76) — null means "run immediately, no
            // scheduled start". Status is set to STATUS_SCHEDULED until this
            // passes; jobs are still dispatched immediately but delayed until
            // this absolute time (see CampaignDispatcher).
            $table->timestamp('scheduled_at')->nullable()->after('media_type');

            // Restricts which hours of day a send is allowed to actually go
            // out — e.g. [9,10,...,18] for "Daytime". Null/empty means any hour.
            $table->json('allowed_hours')->nullable()->after('scheduled_at');

            // "Enable Recurring Schedule" — null means one-off. When set,
            // ProcessRecurringWhatsappCampaigns spins off a fresh child
            // campaign (see parent_campaign_id) each time next_run_at is due.
            $table->string('recurring_frequency')->nullable()->after('allowed_hours'); // daily|weekly|monthly
            $table->timestamp('next_run_at')->nullable()->after('recurring_frequency');

            $table->foreignId('parent_campaign_id')->nullable()->after('contact_group_id')
                ->constrained('whatsapp_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_campaign_id');
            $table->dropColumn(['scheduled_at', 'allowed_hours', 'recurring_frequency', 'next_run_at']);
        });
    }
};
