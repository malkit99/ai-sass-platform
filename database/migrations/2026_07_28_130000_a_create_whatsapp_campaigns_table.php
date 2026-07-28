<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('message_type')->default('text'); // text|media
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->boolean('spintax_enabled')->default(false);
            $table->boolean('warm_up_mode')->default(false);
            $table->unsignedInteger('min_interval_seconds')->default(5);
            $table->unsignedInteger('max_interval_seconds')->default(15);
            $table->string('status')->default('scheduled'); // scheduled|running|completed|failed|cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
