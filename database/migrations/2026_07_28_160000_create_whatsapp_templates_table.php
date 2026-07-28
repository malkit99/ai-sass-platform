<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // text|text_image|text_video|text_document|text_audio|text_buttons|
            // text_lists|text_poll|interactive_buttons|text_carousel — see screenshot 36.
            $table->string('type');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            // Type-specific structured extras (buttons/list sections/poll
            // options/carousel cards) — kept flexible rather than a column
            // per type, since each interactive type's shape differs.
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
