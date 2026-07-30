<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_group_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('whatsapp_groups')->cascadeOnDelete();
            $table->string('phone');
            $table->string('admin')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_group_participants');
    }
};
