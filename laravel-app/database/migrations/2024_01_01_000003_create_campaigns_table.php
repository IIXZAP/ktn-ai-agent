<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('natural_language_query');
            $table->string('industry')->nullable();
            $table->json('locations')->nullable();
            $table->integer('radius_km')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('maximum_leads')->default(50);
            $table->enum('status', [
                'draft',
                'queued',
                'parsing',
                'discovering',
                'finding_websites',
                'crawling',
                'analyzing',
                'scoring',
                'completed',
                'failed',
                'cancelled',
            ])->default('draft');
            $table->tinyInteger('progress_percent')->default(0);
            $table->string('current_stage')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_status');
            $table->index('created_by', 'idx_created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
