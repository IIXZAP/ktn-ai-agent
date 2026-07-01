<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchJobLog extends Model
{
    public $timestamps = false; // มีแค่ created_at

    protected $fillable = [
        'research_job_id',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    // Log เป็นของ ResearchJob
    public function researchJob(): BelongsTo
    {
        return $this->belongsTo(ResearchJob::class);
    }
}
