<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_search_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('industries')->nullable();
            $table->json('industries_type')->nullable();
            $table->string('company_type')->nullable();
            $table->tinyInteger('must_have_website')->default(0);
            $table->json('locations')->nullable();
            $table->json('target_signals')->nullable();
            $table->string('prompt_version')->nullable();
            $table->integer('maximum_leads')->default(50);
            $table->enum('source', ['ai', 'manual', 'mock'])->default('mock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_search_criteria');
    }
};
