<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // Per-instance token for the future public REST API (Phase 1c, see
            // 11-unofficial-whatsapp.md) — shown on the Profile screen now
            // (screenshot 32) alongside the instance ID, same pairing the
            // reference app's own API docs use (instance_id + access_token).
            $table->string('access_token', 40)->nullable()->unique()->after('profile_phone');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
