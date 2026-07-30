<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public REST API's set_webhook (screenshot 39) — lets an external API
 * consumer register their own URL to receive this instance's connection-
 * status/inbound-message events, forwarded alongside (not instead of) the
 * existing internal webhook handling. HMAC-signed with the channel's own
 * access_token as secret (see WebhookController's forwardToExternalWebhook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('external_webhook_url')->nullable()->after('access_token');
            $table->boolean('external_webhook_enabled')->default(false)->after('external_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['external_webhook_url', 'external_webhook_enabled']);
        });
    }
};
