<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('lead_score')->default(0);
            $table->tinyInteger('opportunity_score')->default(0);
            $table->tinyInteger('signal_match_score')->default(0);
            $table->json('matched_signals')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('campaign_id', 'idx_leads_campaign');
            $table->index('lead_score', 'idx_leads_lead_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
