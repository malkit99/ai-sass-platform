<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            // "Enable Emoji Randomizer" (screenshot 76) — appends a random
            // emoji to each recipient's message, same spirit as spintax_enabled
            // (byte-identical bulk messages are more likely to get flagged).
            $table->boolean('emoji_randomizer')->default(false)->after('spintax_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('emoji_randomizer');
        });
    }
};
