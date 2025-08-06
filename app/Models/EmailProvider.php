<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailProvider extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'provider_type',
        'is_valid',
        'is_active',
        'detection_priority',
        // Configuration IMAP
        'imap_host',
        'imap_port',
        'imap_encryption',
        'validate_cert',
        // OAuth
        'supports_oauth',
        'oauth_provider',
        // Rate Limits
        'max_connections_per_hour',
        'max_checks_per_connection',
        'connection_backoff_minutes',
        'supports_idle',
        'check_intervals',
        // Détection
        'domains',
        'mx_patterns',
        // Configuration
        'requires_app_password',
        'instructions',
        'notes',
        'logo_url'
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'is_active' => 'boolean',
        'validate_cert' => 'boolean',
        'supports_oauth' => 'boolean',
        'supports_idle' => 'boolean',
        'requires_app_password' => 'boolean',
        'domains' => 'array',
        'mx_patterns' => 'array',
        'check_intervals' => 'array',
        'detection_priority' => 'integer',
        'imap_port' => 'integer',
        'max_connections_per_hour' => 'integer',
        'max_checks_per_connection' => 'integer',
        'connection_backoff_minutes' => 'integer'
    ];


    /**
     * Comptes email associés à ce provider
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class, 'provider', 'name');
    }


    /**
     * Scope pour les providers actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour ordonner par nom d'affichage
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_name');
    }

    /**
     * Vérifier si le provider est bloqué
     */
    public function isBlocked(): bool
    {
        return !$this->is_valid || in_array($this->provider_type, ['temporary', 'blacklisted', 'discontinued']);
    }

    /**
     * Vérifier si c'est un provider B2C
     */
    public function isB2C(): bool
    {
        return $this->provider_type === 'b2c';
    }

    /**
     * Vérifier si c'est un provider B2B
     */
    public function isB2B(): bool
    {
        return $this->provider_type === 'b2b';
    }
    
    /**
     * Vérifier si c'est un provider custom
     */
    public function isCustom(): bool
    {
        return $this->provider_type === 'custom';
    }
    
    /**
     * Obtenir la configuration IMAP
     */
    public function getImapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port,
            'encryption' => $this->imap_encryption,
            'validate_cert' => $this->validate_cert,
        ];
    }
    

    /**
     * Scope pour les providers valides
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true)
            ->whereNotIn('type', ['temporary', 'blacklisted', 'discontinued']);
    }

    /**
     * Scope pour les providers bloqués
     */
    public function scopeBlocked($query)
    {
        return $query->where(function ($q) {
            $q->where('is_valid', false)
                ->orWhereIn('type', ['temporary', 'blacklisted', 'discontinued']);
        });
    }

    /**
     * Trouver un provider par domaine
     */
    public static function findByDomain(string $domain): ?self
    {
        $domain = strtolower($domain);
        
        // Chercher dans le champ domains JSON
        return self::where(function ($query) use ($domain) {
            $query->whereJsonContains('domains', $domain);
        })
        ->orderBy('detection_priority')
        ->first();
    }

    /**
     * Trouver un provider par MX
     */
    public static function findByMxRecord(string $mxRecord): ?self
    {
        $mxRecord = strtolower($mxRecord);
        
        // Chercher dans mx_patterns JSON
        return self::where(function ($query) use ($mxRecord) {
            $query->whereJsonContains('mx_patterns', $mxRecord);
            
            // Ou avec wildcards
            foreach (['pphosted.com', 'barracudanetworks.com', 'mimecast.com'] as $pattern) {
                if (strpos($mxRecord, $pattern) !== false) {
                    $query->orWhereJsonContains('mx_patterns', $pattern);
                }
            }
        })
        ->orderBy('detection_priority')
        ->first();
    }
}