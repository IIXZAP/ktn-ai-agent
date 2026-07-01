<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPage extends Model
{
    protected $fillable = [
        'company_id',
        'url',
        'http_code',
        'title',
        'meta_description',
        'meta_keywords',
        'load_time_ms',
        'page_speed_score',
        'has_ssl',
        'is_mobile_friendly',
        'crawl_status',
        'crawl_error',
        'crawled_at',
    ];

    protected $casts = [
        'has_ssl'            => 'boolean',
        'is_mobile_friendly' => 'boolean',
        'crawled_at'         => 'datetime',
    ];

    // Page เป็นของ Company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
