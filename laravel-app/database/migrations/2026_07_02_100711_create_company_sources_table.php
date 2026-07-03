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
        Schema::create('company_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('research_job_id')
                ->nullable()
                ->constrained('research_jobs')
                ->nullOnDelete();

            $table->string('source_name')->default('mock')
                ->comment('เช่น mock, google_places, serpapi');

            $table->string('source_external_id')->nullable()
                ->comment('ID ของบริษัทนี้ในระบบต้นทาง ใช้กันข้อมูลซ้ำ');

            $table->json('raw_payload')->nullable()
                ->comment('ข้อมูลดิบจากแหล่งต้นทาง เก็บไว้ debug');

            $table->timestamp('discovered_at')->useCurrent();

            $table->timestamps();

            $table->index('company_id', 'idx_company_source');
            $table->unique(['source_name', 'source_external_id'], 'idx_source_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_sources');
    }
};
