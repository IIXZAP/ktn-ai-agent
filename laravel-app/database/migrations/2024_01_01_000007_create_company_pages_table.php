<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->smallInteger('http_code')->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->integer('load_time_ms')->nullable();
            $table->tinyInteger('page_speed_score')->nullable();
            $table->tinyInteger('has_ssl')->nullable();
            $table->tinyInteger('is_mobile_friendly')->nullable();
            $table->enum('crawl_status', ['pending', 'success', 'failed', 'timeout', 'blocked'])->default('pending');
            $table->text('crawl_error')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index('company_id', 'idx_company_pages_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_pages');
    }
};
