<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Null channel_id = applies to every channel on this account ("apply for all accounts" in the reference app).
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_autoresponders');
    }
};
