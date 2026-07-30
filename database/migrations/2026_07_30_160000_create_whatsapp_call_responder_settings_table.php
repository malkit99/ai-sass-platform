<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_call_responder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->unique()->constrained('channels')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_reject_enabled')->default(true);
            $table->unsignedInteger('reply_delay_seconds')->default(0);
            $table->text('missed_call_reply')->nullable();
            $table->text('after_call_reply')->nullable();
            $table->text('rejected_call_reply')->nullable();
            $table->text('missed_before_answer_reply')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_call_responder_settings');
    }
};
