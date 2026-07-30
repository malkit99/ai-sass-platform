<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_bot_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('name');
            $table->json('trigger_keywords');
            // vue-flow's own {nodes:[...], edges:[...]} shape, stored as-is —
            // zero transformation between canvas state and persisted form.
            $table->json('flow_definition');
            $table->string('status')->default('draft'); // draft|active
            $table->string('source')->default('scratch'); // scratch|template|imported
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('completion_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_flows');
    }
};
