<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chatbot_rules', function (Blueprint $table) {
            // "Send to" individual/group (screenshot 89) — `target` already
            // exists (all|individual|group), these two carry the specifics,
            // same pattern as whatsapp_autoresponders.
            $table->string('target_phone')->nullable()->after('target');
            $table->foreignId('contact_group_id')->nullable()->after('target_phone')
                ->constrained('whatsapp_contact_groups')->nullOnDelete();

            // Buttons/list messages (screenshot 89's tabs) — message_type
            // extends beyond text/media, snapshot config same as
            // whatsapp_autoresponders.interactive_config.
            $table->json('interactive_config')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chatbot_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_group_id');
            $table->dropColumn(['target_phone', 'interactive_config']);
        });
    }
};
