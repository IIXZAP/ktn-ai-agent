<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'company_name',
        'registration_number',
        'industry',
        'web_url',
        'province',
        'address',
        'tel',
        'email',
        'contact_arr',
        'has_website',
        'website_status',
        'website_confidence',
    ];

    protected $casts = [
        'contact_arr'        => 'array',
        'has_website'        => 'boolean',
        'website_confidence' => 'float',
    ];

    // Company เป็นของ Campaign
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // Company มี Page หลายอัน
    public function pages(): HasMany
    {
        return $this->hasMany(CompanyPage::class);
    }

    // Company มี Signal หลายอัน
    public function signals(): HasMany
    {
        return $this->hasMany(CompanySignal::class);
    }

    // Company มี AI Analysis หนึ่งอัน
    public function aiAnalysis(): HasOne
    {
        return $this->hasOne(CompanyAiAnalysis::class);
    }

    // Company กลายเป็น Lead
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }
}
