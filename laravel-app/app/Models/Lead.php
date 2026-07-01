<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'company_id',
        'lead_score',
        'opportunity_score',
        'signal_match_score',
        'matched_signals',
    ];

    protected $casts = [
        'matched_signals' => 'array',
    ];

    // Lead เป็นของ Campaign
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // Lead เป็นของ Company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
