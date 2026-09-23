<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailProvider extends Model
{
    /**
     * Libellés calculés par EmailAccount::getRealProvider() qui n'ont pas de
     * fiche propre : ils héritent du fournisseur dont ils dérivent.
     */
    private const LABEL_ALIASES = [
        'microsoft 365' => 'outlook',
        'google workspace' => 'gmail',
    ];

    /** Codes pays par libellé, chargés une fois par requête. */
    private static ?array $countriesByLabel = null;

    /**
     * Pays (code ISO en minuscules) du fournisseur affiché sous ce libellé,
     * tel que renseigné dans l'admin. Null si inconnu.
     */
    public static function countryForLabel(string $label): ?string
    {
        if (self::$countriesByLabel === null) {
            $providers = self::whereNotNull('country')->get(['name', 'display_name', 'country']);

            self::$countriesByLabel = [];
            foreach ($providers as $provider) {
                self::$countriesByLabel[mb_strtolower($provider->display_name)] = $provider->country;
                self::$countriesByLabel[mb_strtolower($provider->name)] = $provider->country;
            }
        }

        $key = mb_strtolower(trim($label));
        $key = self::LABEL_ALIASES[$key] ?? $key;

        return self::$countriesByLabel[$key] ?? null;
    }

    /**
     * Pays proposés dans l'admin : ceux dont on a le drapeau, nommés dans
     * la langue courante. [code => nom], triés par nom.
     */
    public static function countryOptions(): array
    {
        $locale = app()->getLocale();
        $options = [];

        foreach (glob(public_path('images/flags/*.svg')) as $file) {
            $code = basename($file, '.svg');
            if (strlen($code) !== 2) {
                continue;
            }
            $name = \Locale::getDisplayRegion('-' . strtoupper($code), $locale);
            // Codes sans nom connu (ex. drapeaux régionaux) : ignorés
            if ($name && strcasecmp($name, $code) !== 0) {
                $options[$code] = $name;
            }
        }

        asort($options, SORT_LOCALE_STRING);

        return $options;
    }

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'provider_type',
        'country',
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