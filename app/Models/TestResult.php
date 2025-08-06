<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'email_account_id',
        'message_id',
        'from_email',
        'from_name',
        'subject',
        'body_preview',
        'placement',
        'folder_name',
        'spf_result',
        'dkim_result',
        'dmarc_result',
        'bimi_present',
        'sending_ip',
        'sending_hostname',
        'reverse_dns_valid',
        'blacklist_results',
        'spam_filters_detected',
        'spam_scores',
        'spam_report',
        'has_attachments',
        'has_tracking_pixels',
        'has_suspicious_links',
        'size_bytes',
        'raw_headers',
        'raw_email',
        'email_date',
    ];

    protected $casts = [
        'blacklist_results' => 'array',
        'spam_filters_detected' => 'array',
        'spam_scores' => 'array',
        'bimi_present' => 'boolean',
        'reverse_dns_valid' => 'boolean',
        'has_attachments' => 'boolean',
        'has_tracking_pixels' => 'boolean',
        'has_suspicious_links' => 'boolean',
        'size_bytes' => 'integer',
        'email_date' => 'datetime',
    ];

    protected $hidden = [
        'raw_email', // Hide by default, only show when specifically requested
    ];

    /**
     * Get the test that owns this result
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    /**
     * Get the email account that produced this result
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Determine if the email passed authentication
     */
    public function passedAuthentication(): bool
    {
        return $this->spf_result === 'pass' 
            && $this->dkim_result === 'pass' 
            && $this->dmarc_result === 'pass';
    }

    /**
     * Determine if the email went to inbox
     */
    public function isInbox(): bool
    {
        return in_array(strtolower($this->placement), ['inbox', 'primary']);
    }

    /**
     * Determine if the email went to spam
     */
    public function isSpam(): bool
    {
        return in_array(strtolower($this->placement), ['spam', 'junk', 'quarantine']);
    }

    /**
     * Determine if the email went to promotions/other tabs
     */
    public function isPromotions(): bool
    {
        return in_array(strtolower($this->placement), ['promotions', 'social', 'updates', 'forums']);
    }

    /**
     * Scope by placement
     */
    public function scopeByPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    /**
     * Get authentication score
     */
    public function getAuthenticationScore(): array
    {
        $score = 0;
        $max = 3;

        if ($this->spf_result === 'pass') $score++;
        if ($this->dkim_result === 'pass') $score++;
        if ($this->dmarc_result === 'pass') $score++;

        return [
            'score' => $score,
            'max' => $max,
            'percentage' => ($score / $max) * 100,
            'status' => $score === $max ? 'excellent' : ($score >= 2 ? 'good' : 'poor'),
        ];
    }

    /**
     * Check if has authentication issues
     */
    public function hasAuthenticationIssues(): bool
    {
        return $this->spf_result !== 'pass' || 
               $this->dkim_result !== 'pass' || 
               $this->dmarc_result !== 'pass';
    }

    /**
     * Check if blacklisted
     */
    public function isBlacklisted(): bool
    {
        if (empty($this->blacklist_results)) {
            return false;
        }

        return collect($this->blacklist_results)->contains(true);
    }

    /**
     * Get blacklist count
     */
    public function getBlacklistCount(): int
    {
        if (empty($this->blacklist_results)) {
            return 0;
        }

        return collect($this->blacklist_results)->filter()->count();
    }

    /**
     * Get spam score
     */
    public function getSpamScore(): ?float
    {
        if (empty($this->spam_scores)) {
            return null;
        }

        // Return the highest spam score
        return collect($this->spam_scores)->max();
    }
}