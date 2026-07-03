<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSearchCriteria extends Model
{
    protected $table = 'campaign_search_criteria';

    protected $fillable = [
        'campaign_id',
        'industries',
        'industries_type',
        'company_type',
        'must_have_website',
        'locations',
        'target_signals',
        'prompt_version',
        'maximum_leads',
        'source',
    ];

    protected $casts = [
        'industries'        => 'array',
        'industries_type'   => 'array',
        'locations'         => 'array',
        'target_signals'    => 'array',
        'must_have_website' => 'boolean',
    ];

    // Criteria เป็นของ Campaign
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
