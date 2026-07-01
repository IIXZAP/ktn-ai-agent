<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAiAnalysis extends Model
{
    protected $table = 'company_ai_analyses';

    protected $fillable = [
        'company_id',
        'business_summary',
        'opportunity_score',
        'pain_points',
        'key_findings',
        'recommended_approach',
        'estimated_cost',
        'source',
    ];

    protected $casts = [
        'pain_points'    => 'array',
        'key_findings'   => 'array',
        'estimated_cost' => 'float',
    ];

    // Analysis เป็นของ Company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
