<?php

namespace App\Services;

use App\Models\AntispamSystem;
use App\Models\EmailProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MxDetectionService
{
    /**
     * Analyser une adresse email et détecter son type
     */
    public function analyzeEmail(string $email): array
    {
        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        
        // 1. Vérifier d'abord dans la base de providers
        $provider = EmailProvider::findByDomain($domain);
        
        if ($provider) {
            // Si c'est un provider bloqué
            if ($provider->isBlocked()) {
                return [
                    'valid' => false,
                    'blocked' => true,
                    'reason' => $this->getBlockedReason($provider),
                    'provider' => $provider->toArray()
                ];
            }
            
            // Provider valide
            return [
                'valid' => true,
                'account_type' => $provider->isB2C() ? 'b2c' : 'b2b',
                'confidence' => 'high',
                'reason' => "Provider identifié : {$provider->display_name}",
                'provider' => $provider->toArray(),
                'detected_systems' => $this->getProviderAntispamSystems($provider)
            ];
        }
        
        // 2. Récupérer et analyser les enregistrements MX
        $mxRecords = $this->getMxRecords($domain);
        
        if (empty($mxRecords)) {
            return [
                'valid' => true,
                'account_type' => 'b2b',
                'confidence' => 'low',
                'reason' => 'Aucun enregistrement MX trouvé',
                'detected_systems' => []
            ];
        }
        
        // Analyser les enregistrements MX
        $analysis = $this->analyzeMxRecords($mxRecords);
        
        // Déterminer le type de compte
        if ($analysis['provider']) {
            return [
                'valid' => !$analysis['provider']->isBlocked(),
                'blocked' => $analysis['provider']->isBlocked(),
                'account_type' => $analysis['provider']->isB2C() ? 'b2c' : 'b2b',
                'confidence' => 'high',
                'reason' => $analysis['reason'],
                'provider' => $analysis['provider']->toArray(),
                'detected_systems' => $analysis['antispam_systems'],
                'mx_records' => $mxRecords
            ];
        }
        
        // Par défaut, considérer comme B2B si domaine personnalisé
        return [
            'valid' => true,
            'account_type' => 'b2b',
            'confidence' => 'medium',
            'reason' => 'Domaine professionnel détecté - Sélectionnez B2B pour ce type de domaine',
            'detected_systems' => $analysis['antispam_systems'] ?? [],
            'mx_records' => $mxRecords
        ];
    }
    
    /**
     * Récupérer les enregistrements MX d'un domaine
     */
    private function getMxRecords(string $domain): array
    {
        $cacheKey = "mx_records:{$domain}";
        
        // Utiliser le cache pour éviter des requêtes DNS répétées
        return Cache::remember($cacheKey, 3600, function () use ($domain) {
            $mxRecords = [];
            
            if (getmxrr($domain, $mxHosts, $mxWeights)) {
                // Combiner hosts et weights, puis trier par priorité
                $combined = array_combine($mxHosts, $mxWeights);
                asort($combined);
                
                foreach ($combined as $host => $weight) {
                    $mxRecords[] = [
                        'host' => $host,
                        'priority' => $weight
                    ];
                }
            }
            
            return $mxRecords;
        });
    }
    
    /**
     * Analyser les enregistrements MX
     */
    private function analyzeMxRecords(array $mxRecords): array
    {
        $detectedSystems = [];
        $provider = null;
        $reasons = [];
        
        // Vérifier chaque enregistrement MX
        foreach ($mxRecords as $mx) {
            $mxHost = strtolower($mx['host']);
            
            // Chercher un provider par MX
            if (!$provider) {
                $provider = EmailProvider::findByMxRecord($mxHost);
                if ($provider) {
                    $reasons[] = "Provider {$provider->display_name} détecté";
                }
            }
            
            // Vérifier les systèmes antispam
            $antispamSystems = AntispamSystem::active()->get();
            foreach ($antispamSystems as $system) {
                if ($system->matchesMxRecord($mxHost)) {
                    $detectedSystems[] = [
                        'id' => $system->id,
                        'name' => $system->name,
                        'display_name' => $system->display_name
                    ];
                    $reasons[] = "Filtre {$system->display_name} détecté";
                }
            }
        }
        
        return [
            'provider' => $provider,
            'antispam_systems' => $detectedSystems,
            'reason' => !empty($reasons) ? implode(', ', array_unique($reasons)) : null
        ];
    }
    
    /**
     * Obtenir la raison du blocage
     */
    private function getBlockedReason(EmailProvider $provider): string
    {
        switch ($provider->type) {
            case 'temporary':
                return 'Adresse email temporaire non autorisée';
            case 'blacklisted':
                return 'Domaine blacklisté (possible typo ou domaine invalide)';
            case 'discontinued':
                return 'Service email discontinué';
            default:
                return 'Provider non valide';
        }
    }
    
    /**
     * Obtenir les systèmes antispam associés à un provider
     */
    private function getProviderAntispamSystems(EmailProvider $provider): array
    {
        $systems = [];
        
        // Si c'est un provider antispam, l'ajouter
        if ($provider->type === 'antispam') {
            $antispamSystem = AntispamSystem::where('name', $provider->name)->first();
            if ($antispamSystem) {
                $systems[] = [
                    'id' => $antispamSystem->id,
                    'name' => $antispamSystem->name,
                    'display_name' => $antispamSystem->display_name
                ];
            }
        }
        
        return $systems;
    }
    
    /**
     * Obtenir la recommandation pour un email
     */
    public function getRecommendation(string $email): array
    {
        $analysis = $this->analyzeEmail($email);
        
        return [
            'recommended_type' => $analysis['account_type'],
            'confidence' => $analysis['confidence'],
            'reason' => $analysis['reason'],
            'detected_systems' => $analysis['detected_systems'] ?? []
        ];
    }
}