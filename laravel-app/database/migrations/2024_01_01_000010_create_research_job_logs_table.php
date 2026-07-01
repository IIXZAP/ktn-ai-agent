<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_job_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['debug', 'info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['research_job_id', 'created_at'], 'idx_job_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_job_logs');
    }
};
