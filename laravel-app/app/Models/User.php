<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'email',
        'username',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // User อยู่ใน Department
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // User สร้าง Campaign หลายอัน
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }
}
