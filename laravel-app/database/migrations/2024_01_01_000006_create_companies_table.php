<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('registration_number')->nullable();
            $table->string('industry')->nullable();
            $table->string('web_url')->nullable();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->string('tel')->nullable();
            $table->string('email')->nullable();
            $table->json('contact_arr')->nullable();
            $table->tinyInteger('has_website')->nullable();
            $table->enum('website_status', ['active', 'down', 'unknown'])->nullable();
            $table->decimal('website_confidence', 4, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('campaign_id', 'idx_companies_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
