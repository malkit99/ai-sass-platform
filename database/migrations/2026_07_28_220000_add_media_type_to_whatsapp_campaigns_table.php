<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            // Set when the campaign was built from a saved template (see
            // WhatsappTemplate::mediaKind()) — tells the bridge exactly which
            // media kind to send instead of guessing one from the media_url's
            // file extension (the same fix already applied to single sends).
            $table->string('media_type')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });
    }
};
