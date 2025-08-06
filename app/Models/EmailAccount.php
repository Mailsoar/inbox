<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'name',
        'provider',
        'account_type',
        'auth_type',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_expires_at',
        'last_token_refresh',
        'password',
        'imap_settings',
        'connection_status',
        'last_connection_check',
        'connection_error',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'detected_antispam',
        'folder_mapping',
        'is_active',
        'is_authenticated',
        'disabled_at',
        'disabled_reason',
    ];

    protected $casts = [
        'detected_antispam' => 'array',
        'folder_mapping' => 'array',
        'imap_settings' => 'array',
        'is_active' => 'boolean',
        'is_authenticated' => 'boolean',
        'token_expires_at' => 'datetime',
        'oauth_expires_at' => 'datetime',
        'last_token_refresh' => 'datetime',
        'last_connection_check' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'oauth_token',
        'oauth_refresh_token',
        'password',
        'imap_password',
    ];

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_email_accounts')
            ->withPivot('email_received', 'received_at')
            ->withTimestamps();
    }

    public function receivedEmails(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAuthenticated($query)
    {
        return $query->where('is_authenticated', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function needsTokenRefresh(): bool
    {
        if ($this->auth_type !== 'oauth' || !$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    public function hasAntispamFilter(string $filter): bool
    {
        return in_array($filter, $this->detected_antispam ?? []);
    }
    
    /**
     * Antispam systems associated with this account
     */
    public function antispamSystems(): BelongsToMany
    {
        return $this->belongsToMany(AntispamSystem::class, 'email_account_antispam')
            ->withPivot(['detected_at', 'created_at']);
    }

    /**
     * Email provider associé
     */
    public function emailProvider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'provider', 'name');
    }
    
    
    /**
     * Folder mappings for this account
     */
    public function folderMappings(): HasMany
    {
        return $this->hasMany(EmailFolderMapping::class);
    }
    
    /**
     * Get main inbox folder
     */
    public function getInboxFolder(): ?string
    {
        $mapping = $this->folderMappings()->where('folder_type', 'inbox')->first();
        return $mapping ? $mapping->folder_name : null;
    }
    
    /**
     * Get spam folder
     */
    public function getSpamFolder(): ?string
    {
        $mapping = $this->folderMappings()->where('folder_type', 'spam')->first();
        return $mapping ? $mapping->folder_name : null;
    }
    
    /**
     * Get additional inbox folders
     */
    public function getAdditionalInboxes()
    {
        return $this->folderMappings()->where('is_additional_inbox', true)->get();
    }
    
    // Relation supprimée - utiliser emailProvider() à la place
    
    /**
     * Get display name for the provider
     */
    public function getProviderDisplayName(): string
    {
        // Si c'est un compte IMAP générique avec un fournisseur associé
        if ($this->provider === 'imap' && $this->imapProvider) {
            return $this->imapProvider->display_name;
        }
        
        // Sinon, utiliser les noms standards
        $providerNames = [
            'gmail' => 'Gmail',
            'outlook' => 'Outlook',
            'microsoft' => 'Microsoft',
            'yahoo' => 'Yahoo',
            'imap' => 'IMAP',
        ];
        
        return $providerNames[$this->provider] ?? ucfirst($this->provider);
    }
    
    /**
     * Get the real provider name based on domain and MX records
     */
    public function getRealProvider(): string
    {
        $domain = substr(strrchr($this->email, '@'), 1);
        
        // D'abord utiliser le helper pour détecter le provider basé sur domaine/MX
        $detectedProvider = \App\Helpers\EmailHelper::detectProviderFromEmail($this->email);
        
        // Si c'est un domaine personnalisé avec OAuth
        if ($this->auth_type === 'oauth') {
            // Récupérer les domaines standards du provider OAuth
            $standardDomains = [];
            if ($this->emailProvider && $this->emailProvider->domains) {
                $domains = is_string($this->emailProvider->domains) ? 
                    json_decode($this->emailProvider->domains, true) : 
                    $this->emailProvider->domains;
                if (is_array($domains)) {
                    $standardDomains = $domains;
                }
            }
            
            // Pour Gmail OAuth avec domaine personnalisé → Google Workspace
            if ($this->provider === 'gmail') {
                if (!in_array($domain, $standardDomains)) {
                    return 'Google Workspace';
                }
                // Domaine Gmail standard
                return $detectedProvider ?: 'Gmail';
            }
            
            // Pour Outlook/Microsoft OAuth avec domaine personnalisé → Microsoft 365
            if ($this->provider === 'outlook' || $this->provider === 'microsoft') {
                if (!in_array($domain, $standardDomains)) {
                    // Domaine personnalisé
                    // Si c'est détecté comme Outlook/Hotmail par MX, c'est en fait Microsoft 365
                    if ($detectedProvider === 'Outlook / Hotmail' || $detectedProvider === 'Outlook') {
                        return 'Microsoft 365';
                    }
                    // Si un autre provider est détecté par MX (comme Proofpoint), l'utiliser
                    return $detectedProvider ?: 'Microsoft 365';
                }
                // Domaine Microsoft standard
                return $detectedProvider ?: 'Outlook / Hotmail';
            }
        }
        
        // Si un provider est détecté par domaine/MX, le retourner
        if ($detectedProvider) {
            return $detectedProvider;
        }
        
        // Fallback: utiliser le display_name du provider configuré
        if ($this->emailProvider) {
            return $this->emailProvider->display_name;
        }
        
        // Pour les comptes IMAP sans provider identifié
        if ($this->provider === 'imap') {
            return 'Autres';
        }
        
        return ucfirst($this->provider);
    }
}