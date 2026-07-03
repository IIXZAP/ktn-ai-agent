<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySource extends Model
{
    protected $fillable = [
        'company_id',
        'research_job_id',
        'source_name',
        'source_external_id',
        'raw_payload',
        'discovered_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'discovered_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function researchJob(): BelongsTo
    {
        return $this->belongsTo(ResearchJob::class);
    }
}
