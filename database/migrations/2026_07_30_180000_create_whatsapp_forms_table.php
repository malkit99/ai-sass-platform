<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft'); // draft|active
            $table->json('fields');
            $table->json('automation_config')->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            // Stub — no payment/commerce field type exists yet and no
            // Commerce module exists to write to this; stays a real,
            // legitimately-zero aggregate rather than a faked value (see
            // 11-unofficial-whatsapp.md).
            $table->decimal('revenue', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_forms');
    }
};
