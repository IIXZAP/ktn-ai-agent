<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySignal extends Model
{
    protected $fillable = [
        'company_id',
        'signal_type',
        'signal_value',
        'confidence',
        'source_stage',
        'detected_at',
    ];

    protected $casts = [
        'confidence'  => 'float',
        'detected_at' => 'datetime',
    ];

    // Signal เป็นของ Company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
