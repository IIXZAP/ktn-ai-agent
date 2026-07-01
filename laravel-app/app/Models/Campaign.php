<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    // All statuses (match กับ Python pipeline)
    public const STATUSES = [
        'draft',
        'queued',
        'parsing',
        'discovering',
        'finding_websites',
        'crawling',
        'analyzing',
        'scoring',
        'completed',
        'in_progress',
        'failed',
        'cancelled',
    ];

    // กำลังทำงานอยู่
    public const ACTIVE_STATUSES = [
        'queued',
        'parsing',
        'discovering',
        'finding_websites',
        'crawling',
        'analyzing',
        'scoring',
    ];

    // จบแล้ว (ไม่ว่าจะสำเร็จหรือไม่)
    public const TERMINAL_STATUSES = [
        'completed',
        'failed',
        'cancelled',
    ];

    protected $fillable = [
        'created_by',
        'title',
        'natural_language_query',
        'industry',
        'locations',
        'radius_km',
        'latitude',
        'longitude',
        'maximum_leads',
        'status',
        'progress_percent',
        'current_stage',
        'last_error',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'locations'    => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Campaign สร้างโดย User
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Campaign มี SearchCriteria หนึ่งอัน
    public function searchCriteria(): HasOne
    {
        return $this->hasOne(CampaignSearchCriteria::class);
    }

    // Campaign มี ResearchJob หลายอัน
    public function researchJobs(): HasMany
    {
        return $this->hasMany(ResearchJob::class);
    }

    // Campaign มี Company หลายอัน
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    // Campaign มี Lead หลายอัน
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    // Campaign มี AiUsageLog หลายอัน
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
