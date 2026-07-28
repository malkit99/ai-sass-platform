<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            // Small gray text under the body, same as Meta's own WhatsApp
            // template structure (Header/Body/Footer/Buttons) — max 60 chars
            // matches Meta's real footer character limit.
            $table->string('footer', 60)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn('footer');
        });
    }
};
