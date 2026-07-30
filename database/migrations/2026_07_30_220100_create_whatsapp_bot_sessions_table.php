<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_bot_sessions', function (Blueprint $table) {
            $table->id();
            // One row per conversation, reused (overwritten in place) across
            // every bot interaction that conversation ever has — a real
            // unique constraint here (not just an app-level convention)
            // keeps that invariant safe under concurrent webhook deliveries.
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('bot_flow_id')->constrained('whatsapp_bot_flows')->cascadeOnDelete();
            $table->string('current_node_id')->nullable();
            $table->json('variables')->nullable();
            $table->string('status')->default('active'); // active|completed|abandoned
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_sessions');
    }
};
