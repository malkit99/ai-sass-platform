<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dedicated table for the fast-read cached balance (kept out of `accounts`
        // itself, matching this project's preference for new concerns living in
        // their own table rather than bolted onto core tables).
        Schema::create('whatsapp_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits_remaining')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason');
            $table->unsignedInteger('balance_after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_credit_ledger');
        Schema::dropIfExists('whatsapp_credit_balances');
    }
};
