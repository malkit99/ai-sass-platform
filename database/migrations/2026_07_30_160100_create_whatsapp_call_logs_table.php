<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('call_id');
            $table->string('caller_phone');
            $table->boolean('is_video')->default(false);
            // ringing|answered|auto_rejected|manually_rejected|missed|completed
            $table->string('status')->default('ringing');
            $table->string('reply_type')->nullable();
            // Explicit default (not just NOT NULL with none) — MySQL silently
            // attaches DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            // to a bare NOT NULL timestamp column, which would silently
            // overwrite this on every unrelated update to the row (see the
            // 2026_07_30_170000 fix migration for the live-DB version of
            // this same fix).
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'call_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_call_logs');
    }
};
