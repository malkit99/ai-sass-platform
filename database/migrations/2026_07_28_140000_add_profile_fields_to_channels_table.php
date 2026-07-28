<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // Populated from the bridge once the WhatsApp socket authenticates
            // (its own push name + phone number) — lets the UI show "Macroword
            // (919628061241)" instead of a generic "Account #7" placeholder.
            $table->string('profile_name')->nullable()->after('name');
            $table->string('profile_phone')->nullable()->after('profile_name');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['profile_name', 'profile_phone']);
        });
    }
};
