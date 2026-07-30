<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_forms', function (Blueprint $table) {
            $table->text('success_message')->nullable()->after('automation_config');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_forms', function (Blueprint $table) {
            $table->dropColumn('success_message');
        });
    }
};
