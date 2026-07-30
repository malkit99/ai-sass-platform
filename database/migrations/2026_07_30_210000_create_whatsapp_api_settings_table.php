<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reseller-controlled gate for the Public REST API (screenshots 38-50) — a
 * reseller decides which of Instance/Message/Group API sections their own
 * client accounts can see and call. One row per reseller (unique), mirrors
 * `reseller_branding`'s shape/guard pattern (see WhatsappApiSettings model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_api_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->json('enabled_groups')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_api_settings');
    }
};
