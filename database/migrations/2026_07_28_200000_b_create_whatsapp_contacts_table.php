<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_group_id')->constrained('whatsapp_contact_groups')->cascadeOnDelete();
            $table->string('phone');
            $table->string('name')->nullable();
            // {"param1": "...", ..., "param20": "..."} — the Phone/Name/Param1-20
            // import-sheet columns feed straight into bulk-campaign message
            // variable substitution.
            $table->json('params')->nullable();
            $table->string('status')->default('unknown'); // unknown|valid|invalid
            $table->timestamps();

            $table->unique(['contact_group_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
