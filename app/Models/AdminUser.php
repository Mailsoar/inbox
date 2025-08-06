<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'google_id',
        'avatar',
        'is_active',
        'last_login_at',
        'dashboard_period',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isAllowedEmail(): bool
    {
        $allowedEmails = explode(',', config('services.google.allowed_emails', ''));
        $allowedEmails = array_map('trim', $allowedEmails);
        
        return in_array($this->email, $allowedEmails);
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}