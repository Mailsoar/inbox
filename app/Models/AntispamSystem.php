<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AntispamSystem extends Model
{
    protected $table = 'antispam_systems';
    
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'header_patterns',
        'mx_patterns',
        'is_custom',
        'is_active'
    ];
    
    protected $casts = [
        'header_patterns' => 'array',
        'mx_patterns' => 'array',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Email accounts using this antispam system
     */
    public function emailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(EmailAccount::class, 'email_account_antispam')
            ->withPivot(['detected_at', 'created_at']);
    }
    
    /**
     * Check if headers match this antispam system
     */
    public function matchesHeaders(string $headers): bool
    {
        if (empty($this->header_patterns)) {
            return false;
        }
        
        foreach ($this->header_patterns as $pattern) {
            // Check if pattern contains regex special characters
            if (preg_match('/[.*+?^${}()\[\]\\|]/', $pattern)) {
                // Treat as regex pattern
                try {
                    if (preg_match('/' . $pattern . '/i', $headers)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // If regex is invalid, fall back to literal search
                    if (stripos($headers, $pattern) !== false) {
                        return true;
                    }
                }
            } else {
                // Treat as literal string
                if (stripos($headers, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get active systems
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Get custom systems
     */
    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }
    
    /**
     * Get built-in systems
     */
    public function scopeBuiltIn($query)
    {
        return $query->where('is_custom', false);
    }
    
    /**
     * Check if MX record matches this antispam system
     */
    public function matchesMxRecord(string $mxRecord): bool
    {
        if (empty($this->mx_patterns)) {
            return false;
        }
        
        $mxLower = strtolower($mxRecord);
        
        foreach ($this->mx_patterns as $pattern) {
            $patternLower = strtolower($pattern);
            
            // Check if it's a wildcard pattern
            if (strpos($patternLower, '*') !== false) {
                // Convert wildcard to regex
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $patternLower) . '$/i';
                if (preg_match($regex, $mxLower)) {
                    return true;
                }
            } else {
                // Check if pattern is contained in MX record
                if (strpos($mxLower, $patternLower) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
}