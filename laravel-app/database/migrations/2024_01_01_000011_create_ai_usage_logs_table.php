<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('operation');
            $table->string('model')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->integer('duration_ms')->default(0);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index('campaign_id', 'idx_ai_logs_campaign');
            $table->index('created_at', 'idx_ai_logs_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
