<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reseller_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('ssl_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_domains');
    }
};
