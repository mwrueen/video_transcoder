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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('original_disk')->default('local');
            $table->bigInteger('file_size')->unsigned();
            $table->string('mime_type');
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('hls_path')->nullable();
            $table->string('hls_url')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_key')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('transcoding_started_at')->nullable();
            $table->timestamp('transcoding_completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
