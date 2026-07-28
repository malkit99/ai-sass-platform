<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->foreignId('contact_group_id')->nullable()->after('channel_id')
                ->constrained('whatsapp_contact_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_group_id');
        });
    }
};
