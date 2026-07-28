<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status')->default('sent');
            $table->string('sent_by')->nullable();
            $table->string('external_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
