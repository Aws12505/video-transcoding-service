<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcode_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('project_key');
            $table->string('video_id');
            $table->json('qualities_requested');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('callback_url');
            $table->json('output_paths')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['project_key', 'video_id']);
            $table->index('status');
            $table->foreign('project_key')->references('project_key')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcode_jobs');
    }
};
