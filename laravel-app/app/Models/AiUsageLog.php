<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false; // มีแค่ created_at

    protected $fillable = [
        'campaign_id',
        'company_id',
        'operation',
        'model',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'duration_ms',
        'status',
    ];

    protected $casts = [
        'estimated_cost' => 'float',
        'created_at'     => 'datetime',
    ];

    // Log เป็นของ Campaign
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
