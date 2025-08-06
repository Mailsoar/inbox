<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_id',
        'visitor_email',
        'visitor_ip',
        'test_type',
        'specific_target',
        'audience_type',
        'expected_emails',
        'received_emails',
        'status',
        'language',
        'timeout_at',
        'expires_at',
    ];

    protected $casts = [
        'expected_emails' => 'integer',
        'received_emails' => 'integer',
        'timeout_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($test) {
            if (empty($test->unique_id)) {
                $test->unique_id = static::generateUniqueId();
            }
            
            if (empty($test->timeout_at)) {
                $test->timeout_at = now()->addMinutes(config('mailsoar.email_check_timeout_minutes', 30));
            }
            
            if (empty($test->expires_at)) {
                $test->expires_at = now()->addDays(config('mailsoar.test_retention_days', 7));
            }
        });
    }

    public function emailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(EmailAccount::class, 'test_email_accounts')
            ->withPivot('email_received', 'received_at')
            ->withTimestamps();
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }
    
    // Alias pour compatibilité temporaire
    public function receivedEmails(): HasMany
    {
        return $this->results();
    }

    public static function generateUniqueId(): string
    {
        do {
            $id = strtoupper(Str::random(10));
        } while (static::where('unique_id', $id)->exists());

        return $id;
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeTimedOut($query)
    {
        return $query->where('status', 'pending')
            ->where('timeout_at', '<=', now());
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isTimedOut(): bool
    {
        return $this->status === 'pending' && $this->timeout_at->isPast();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function updateProgress(): void
    {
        $receivedCount = $this->emailAccounts()
            ->wherePivot('email_received', true)
            ->count();

        $this->received_emails = $receivedCount;

        if ($receivedCount >= $this->expected_emails) {
            $this->status = 'completed';
        } elseif ($receivedCount > 0 && $this->status === 'pending') {
            $this->status = 'in_progress';
        }

        $this->save();
    }

    /**
     * Get all placement values that should be counted as "inbox"
     * Based on email_folder_mappings table
     */
    public static function getInboxPlacements(): array
    {
        $placements = ['inbox']; // Always include 'inbox'
        
        // Get all additional inbox mappings from database
        $additionalMappings = \DB::table('email_folder_mappings')
            ->where('folder_type', 'additional_inbox')
            ->distinct()
            ->pluck('display_name');
        
        foreach ($additionalMappings as $displayName) {
            $placement = strtolower($displayName);
            if (!in_array($placement, $placements)) {
                $placements[] = $placement;
            }
        }
        
        return $placements;
    }

    /**
     * Get inbox count for this test using dynamic mappings
     */
    public function getInboxCount(): int
    {
        $inboxPlacements = self::getInboxPlacements();
        return $this->receivedEmails()->whereIn('placement', $inboxPlacements)->count();
    }

    /**
     * Get spam count for this test
     */
    public function getSpamCount(): int
    {
        return $this->receivedEmails()->where('placement', 'spam')->count();
    }
    
}