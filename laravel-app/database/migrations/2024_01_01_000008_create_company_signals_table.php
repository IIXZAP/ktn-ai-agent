<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('signal_type');
            $table->string('signal_value')->nullable();
            $table->decimal('confidence', 4, 3)->default(1.000);
            $table->enum('source_stage', ['crawling', 'ai_analysis', 'manual'])->default('crawling');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            $table->index(['company_id', 'signal_type'], 'idx_company_signal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_signals');
    }
};
