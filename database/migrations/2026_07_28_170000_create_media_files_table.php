<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('disk'); // local|r2|s3 — whichever config/filesystems.php disk actually stored it
            $table->string('path'); // storage path/key on that disk
            $table->string('url'); // public URL, computed at upload time
            $table->string('name'); // original filename shown in the UI
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // bytes
            $table->string('type'); // image|video|audio|document — derived from mime_type
            $table->timestamps();

            $table->index(['account_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
