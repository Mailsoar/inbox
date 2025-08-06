<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FilterRule extends Model
{
    protected $fillable = [
        'type',
        'value',
        'action',
        'is_active',
        'description',
        'options'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array'
    ];

    /**
     * Types de règles disponibles
     */
    const TYPE_IP = 'ip';
    const TYPE_DOMAIN = 'domain';
    const TYPE_MX = 'mx';
    const TYPE_EMAIL_PATTERN = 'email_pattern';

    /**
     * Actions disponibles
     */
    const ACTION_BLOCK = 'block';
    const ACTION_ALLOW = 'allow';

    /**
     * Scope pour les règles actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Vérifier si une IP est bloquée
     */
    public static function isIpBlocked($ip)
    {
        return Cache::remember("filter_ip_{$ip}", 300, function () use ($ip) {
            // Vérifier les règles de blocage
            $blocked = self::active()
                ->ofType(self::TYPE_IP)
                ->where('action', self::ACTION_BLOCK)
                ->where(function ($query) use ($ip) {
                    $query->where('value', $ip)
                        ->orWhere(function ($q) use ($ip) {
                            // Support des wildcards et CIDR
                            $q->where('value', 'LIKE', '%*%')
                              ->whereRaw("? LIKE REPLACE(value, '*', '%')", [$ip]);
                        });
                })
                ->exists();

            if ($blocked) {
                // Vérifier s'il y a une règle d'autorisation qui override
                return !self::active()
                    ->ofType(self::TYPE_IP)
                    ->where('action', self::ACTION_ALLOW)
                    ->where('value', $ip)
                    ->exists();
            }

            return false;
        });
    }

    /**
     * Vérifier si un domaine est bloqué
     */
    public static function isDomainBlocked($domain)
    {
        return Cache::remember("filter_domain_{$domain}", 300, function () use ($domain) {
            return self::active()
                ->ofType(self::TYPE_DOMAIN)
                ->where('action', self::ACTION_BLOCK)
                ->where(function ($query) use ($domain) {
                    $query->where('value', $domain)
                        ->orWhere('value', '*.' . $domain)
                        ->orWhereRaw("? LIKE CONCAT('%', value)", [$domain]);
                })
                ->exists();
        });
    }

    /**
     * Obtenir les options de normalisation
     */
    public static function getNormalizationOptions()
    {
        return Cache::remember('filter_normalization_options', 3600, function () {
            $rule = self::active()
                ->ofType(self::TYPE_EMAIL_PATTERN)
                ->where('value', 'normalization_settings')
                ->first();

            return $rule ? $rule->options : [
                'normalize_gmail_dots' => true,
                'normalize_plus_aliases' => true,
                'gmail_domains' => ['gmail.com', 'googlemail.com'],
                'outlook_domains' => ['outlook.com', 'hotmail.com', 'live.com', 'msn.com']
            ];
        });
    }

    /**
     * Clear cache when model is saved
     */
    protected static function booted()
    {
        static::saved(function ($model) {
            Cache::flush(); // On pourrait être plus sélectif ici
        });

        static::deleted(function ($model) {
            Cache::flush();
        });
    }
}