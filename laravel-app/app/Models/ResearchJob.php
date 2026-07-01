<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchJob extends Model
{
    protected $fillable = [
        'campaign_id',
        'status',
        'progress_percent',
        'current_stage',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    // ResearchJob เป็นของ Campaign
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // ResearchJob มี Log หลายอัน
    public function logs(): HasMany
    {
        return $this->hasMany(ResearchJobLog::class);
    }
}
