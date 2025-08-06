<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'last_used_at',
        'session_started_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'session_started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a new unique token
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Check if the device is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(): bool
    {
        $this->last_used_at = now();
        return $this->save();
    }

    /**
     * Scope for active devices
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Get device display name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->device_name) {
            return $this->device_name;
        }

        $parts = [];
        if ($this->browser) {
            $parts[] = $this->browser;
        }
        if ($this->platform) {
            $parts[] = $this->platform;
        }
        
        return implode(' - ', $parts) ?: 'Unknown Device';
    }
}