<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Global Form Configuration" (screenshots 98-102) — the General tab's
 * "Success Action"/"WhatsApp Account" fields get real top-level columns
 * since PublicFormView reads them directly; everything else (WhatsApp
 * notify/reply templates, CRM/AI/Payment/IVR tab settings) stays inside the
 * existing `automation_config` json column, restructured to a richer shape
 * — see FormPublicController's docblock for exactly which of those are real
 * vs. deliberate stubs (Payment/IVR/AI Qualification/round-robin assignment
 * have no backing systems built yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_forms', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('account_id')->constrained('channels')->nullOnDelete();
            $table->string('success_action')->default('message')->after('success_message'); // message|redirect
            $table->string('success_redirect_url')->nullable()->after('success_action');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
            $table->dropColumn(['success_action', 'success_redirect_url']);
        });
    }
};
