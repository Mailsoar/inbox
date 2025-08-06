<?php

namespace App\Helpers;

use App\Models\FilterRule;

class EmailHelper
{
    /**
     * Normalise un email en utilisant les règles dynamiques
     * 
     * @param string $email
     * @return string
     */
    public static function normalize(string $email): string
    {
        $email = strtolower(trim($email));
        
        // Séparer l'email en parties
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        [$localPart, $domain] = $parts;
        
        // Récupérer les options de normalisation depuis la base de données
        $options = FilterRule::getNormalizationOptions();
        
        // Gérer les alias avec + si activé
        if ($options['normalize_plus_aliases'] ?? true) {
            if (($plusPos = strpos($localPart, '+')) !== false) {
                $localPart = substr($localPart, 0, $plusPos);
            }
        }
        
        // Gérer les domaines Gmail
        $gmailDomains = $options['gmail_domains'] ?? ['gmail.com', 'googlemail.com'];
        if (in_array($domain, $gmailDomains)) {
            // Supprimer les points si activé
            if ($options['normalize_gmail_dots'] ?? true) {
                $localPart = str_replace('.', '', $localPart);
            }
        }
        
        return $localPart . '@' . $domain;
    }
    
    /**
     * Vérifie si un email est bloqué
     * 
     * @param string $email
     * @return bool
     */
    public static function isBlocked(string $email): bool
    {
        $domain = substr($email, strpos($email, '@') + 1);
        return FilterRule::isDomainBlocked($domain);
    }
    
    /**
     * Vérifie si une IP est bloquée
     * 
     * @param string $ip
     * @return bool
     */
    public static function isIpBlocked(string $ip): bool
    {
        return FilterRule::isIpBlocked($ip);
    }
    
    /**
     * Vérifie si deux emails sont équivalents (même email normalisé)
     * 
     * @param string $email1
     * @param string $email2
     * @return bool
     */
    public static function areEquivalent(string $email1, string $email2): bool
    {
        return self::normalize($email1) === self::normalize($email2);
    }
    
    /**
     * Détecte le fournisseur d'email basé sur le domaine et les MX records
     * 
     * @param string $email
     * @return string|null
     */
    public static function detectProviderFromEmail(string $email): ?string
    {
        $domain = substr(strrchr($email, '@'), 1);
        if (!$domain) {
            return null;
        }
        
        // Chercher d'abord par domaine exact dans la base
        $providers = \App\Models\EmailProvider::where('is_active', true)
            ->orderBy('detection_priority', 'asc')
            ->get();
        
        foreach ($providers as $provider) {
            if ($provider->domains) {
                $domains = is_string($provider->domains) ? json_decode($provider->domains, true) : $provider->domains;
                if (is_array($domains) && in_array($domain, $domains)) {
                    return $provider->display_name;
                }
            }
        }
        
        // Si pas trouvé par domaine, chercher par MX records
        $mxHosts = self::getMXRecords($domain);
        if (!empty($mxHosts)) {
            foreach ($providers as $provider) {
                if ($provider->mx_patterns) {
                    $patterns = is_string($provider->mx_patterns) ? json_decode($provider->mx_patterns, true) : $provider->mx_patterns;
                    if (is_array($patterns)) {
                        foreach ($patterns as $pattern) {
                            foreach ($mxHosts as $mxHost) {
                                // Vérifier si le pattern matche le MX host
                                if (stripos($mxHost, $pattern) !== false) {
                                    return $provider->display_name;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Récupère les enregistrements MX d'un domaine
     * 
     * @param string $domain
     * @return array
     */
    public static function getMXRecords(string $domain): array
    {
        $mxHosts = [];
        
        try {
            // Utiliser getmxrr pour récupérer les MX records
            $mxRecords = [];
            $weights = [];
            
            if (getmxrr($domain, $mxRecords, $weights)) {
                // Combiner et trier par priorité
                $combined = array_combine($mxRecords, $weights);
                asort($combined);
                $mxHosts = array_keys($combined);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, essayer avec dns_get_record
            try {
                $records = dns_get_record($domain, DNS_MX);
                if ($records) {
                    // Trier par priorité
                    usort($records, function($a, $b) {
                        return ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0);
                    });
                    
                    foreach ($records as $record) {
                        if (isset($record['target'])) {
                            $mxHosts[] = $record['target'];
                        }
                    }
                }
            } catch (\Exception $e2) {
                // Ignorer les erreurs DNS
            }
        }
        
        return $mxHosts;
    }
}