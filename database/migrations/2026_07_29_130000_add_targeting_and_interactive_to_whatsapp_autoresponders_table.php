<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_autoresponders', function (Blueprint $table) {
            // "Sent to" (screenshot 77) — all inbound senders, one specific
            // number, or anyone in a contact group.
            $table->string('target')->default('all')->after('enabled'); // all|individual|group
            $table->string('target_phone')->nullable()->after('target');
            $table->foreignId('contact_group_id')->nullable()->after('target_phone')
                ->constrained('whatsapp_contact_groups')->nullOnDelete();

            // Buttons/list templates authored inline (message_type extends
            // beyond text/media to buttons/list) — snapshot config, same
            // pattern as whatsapp_campaigns.interactive_config.
            $table->json('interactive_config')->nullable()->after('media_url');

            // "Resubmit message only after (minute)" (screenshot 78) — cooldown
            // before the same conversation gets auto-replied to again.
            $table->unsignedInteger('resubmit_after_minutes')->default(1)->after('interactive_config');

            // "Except contacts" (screenshot 78) — phone numbers that never get
            // an auto-reply regardless of target.
            $table->json('except_contacts')->nullable()->after('resubmit_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_autoresponders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_group_id');
            $table->dropColumn(['target', 'target_phone', 'interactive_config', 'resubmit_after_minutes', 'except_contacts']);
        });
    }
};
