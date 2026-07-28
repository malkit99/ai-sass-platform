<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chatbot_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('target')->default('all'); // all|individual|group
            $table->string('match_type')->default('contains'); // contains|exact
            $table->string('name');
            $table->json('keywords');
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chatbot_rules');
    }
};
