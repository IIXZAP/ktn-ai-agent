<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('business_summary')->nullable();
            $table->tinyInteger('opportunity_score')->nullable();
            $table->json('pain_points')->nullable();
            $table->json('key_findings')->nullable();
            $table->text('recommended_approach')->nullable();
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->enum('source', ['ai', 'mock'])->default('mock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_ai_analyses');
    }
};
