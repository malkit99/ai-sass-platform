<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Groups files into separate buckets (whatsapp_media, exports, ...)
            // per App\Services\Media\MediaStorage — existing rows default to
            // whatsapp_media since that's the only purpose in use so far.
            $table->string('purpose')->default('whatsapp_media')->after('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
