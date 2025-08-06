<?php

namespace App\Services;

use App\Models\AntispamSystem;
use App\Models\EmailAccount;
use Illuminate\Support\Facades\Log;

class HeaderAnalyzer
{
    /**
     * Analyze headers for all configured antispam systems of an email account
     */
    public static function analyzeForAccount(EmailAccount $emailAccount, string $headers): array
    {
        $detected = [];
        $evidence = [];
        
        // Get all antispam systems associated with this account
        $antispamSystems = $emailAccount->antispamSystems()->get();
        
        if ($antispamSystems->isEmpty()) {
            // If no specific systems are configured, analyze against all active systems
            $antispamSystems = AntispamSystem::active()->get();
        }
        
        // Analyze headers for each configured system
        foreach ($antispamSystems as $system) {
            if ($system->matchesHeaders($headers)) {
                $detected[$system->name] = true;
                
                // Find matching patterns as evidence
                foreach ($system->header_patterns as $pattern) {
                    $lines = explode("\n", $headers);
                    foreach ($lines as $line) {
                        if (self::patternMatches($pattern, $line)) {
                            $evidence[$system->name][] = trim($line);
                        }
                    }
                }
            }
        }
        
        return [
            'detected' => $detected,
            'evidence' => $evidence
        ];
    }
    
    /**
     * Analyze headers for all active antispam systems (for detection phase)
     */
    public static function analyzeAll(string $headers): array
    {
        $detected = [];
        $evidence = [];
        
        // Get all active antispam systems
        $antispamSystems = AntispamSystem::active()->get();
        
        // Analyze headers for each system
        foreach ($antispamSystems as $system) {
            if ($system->matchesHeaders($headers)) {
                $detected[$system->name] = true;
                
                // Find matching patterns as evidence
                foreach ($system->header_patterns as $pattern) {
                    $lines = explode("\n", $headers);
                    foreach ($lines as $line) {
                        if (self::patternMatches($pattern, $line)) {
                            $evidence[$system->name][] = trim($line);
                        }
                    }
                }
            }
        }
        
        return [
            'detected' => $detected,
            'evidence' => $evidence
        ];
    }
    
    /**
     * Check if a pattern matches text
     */
    private static function patternMatches(string $pattern, string $text): bool
    {
        // Check if pattern contains regex special characters
        if (preg_match('/[.*+?^${}()\[\]\\|]/', $pattern)) {
            // Treat as regex pattern
            try {
                return preg_match('/' . $pattern . '/i', $text);
            } catch (\Exception $e) {
                // If regex is invalid, fall back to literal search
                return stripos($text, $pattern) !== false;
            }
        } else {
            // Treat as literal string
            return stripos($text, $pattern) !== false;
        }
    }
}