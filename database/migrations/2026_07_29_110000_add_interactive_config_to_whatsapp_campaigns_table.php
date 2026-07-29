<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            // Snapshot of the resolved template's poll/buttons/list config at
            // dispatch time (WhatsappTemplate::buildInteractiveConfig()) — same
            // "copy, don't reference" pattern as body/media_url, so editing or
            // deleting the source template later can't change an already-queued
            // campaign. Null for plain text/media campaigns.
            $table->json('interactive_config')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('interactive_config');
        });
    }
};
