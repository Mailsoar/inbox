<?php

namespace App\Services;

use App\Models\VerificationRateLimit;
use Carbon\Carbon;

class VerificationRateLimiter
{
    const EMAIL_LIMIT = 3;
    const IP_LIMIT = 5;
    const RESET_HOURS = 24;

    /**
     * Vérifier si une demande est autorisée
     */
    public static function canRequest(string $email, string $ip): array
    {
        $emailCheck = self::checkLimit($email, 'email', self::EMAIL_LIMIT);
        $ipCheck = self::checkLimit($ip, 'ip', self::IP_LIMIT);

        if (!$emailCheck['allowed'] || !$ipCheck['allowed']) {
            return [
                'allowed' => false,
                'message' => !$emailCheck['allowed'] ? $emailCheck['message'] : $ipCheck['message'],
                'reset_at' => !$emailCheck['allowed'] ? $emailCheck['reset_at'] : $ipCheck['reset_at'],
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Incrémenter le compteur après une demande
     */
    public static function increment(string $email, string $ip): void
    {
        self::incrementCounter($email, 'email');
        self::incrementCounter($ip, 'ip');
    }

    /**
     * Vérifier une limite spécifique
     */
    private static function checkLimit(string $identifier, string $type, int $limit): array
    {
        $record = VerificationRateLimit::firstOrNew([
            'identifier' => $identifier,
            'type' => $type,
        ]);

        // Si pas de record ou reset_at dépassé, on autorise
        if (!$record->exists || $record->reset_at->isPast()) {
            return ['allowed' => true];
        }

        if ($record->attempts >= $limit) {
            // Calculer le temps restant
            $now = now();
            $diff = $record->reset_at->diff($now);
            
            // Formater le temps restant selon la langue
            $locale = app()->getLocale();
            $timeRemaining = '';
            
            if ($locale === 'fr') {
                if ($diff->h > 0) {
                    $timeRemaining = $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
                    if ($diff->i > 0) {
                        $timeRemaining .= ' et ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                    }
                } elseif ($diff->i > 0) {
                    $timeRemaining = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                } else {
                    $timeRemaining = 'moins d\'une minute';
                }
                
                $message = $type === 'email' 
                    ? "Trop de demandes pour cet email. Veuillez réessayer dans {$timeRemaining} ou contacter le support."
                    : "Trop de demandes depuis cette adresse IP. Veuillez réessayer dans {$timeRemaining} ou contacter le support.";
            } else {
                // English
                if ($diff->h > 0) {
                    $timeRemaining = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
                    if ($diff->i > 0) {
                        $timeRemaining .= ' and ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                    }
                } elseif ($diff->i > 0) {
                    $timeRemaining = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                } else {
                    $timeRemaining = 'less than a minute';
                }
                
                $message = $type === 'email' 
                    ? "Too many requests for this email. Please try again in {$timeRemaining} or contact support."
                    : "Too many requests from this IP address. Please try again in {$timeRemaining} or contact support.";
            }
            
            return [
                'allowed' => false,
                'message' => $message,
                'reset_at' => $record->reset_at,
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Incrémenter un compteur
     */
    private static function incrementCounter(string $identifier, string $type): void
    {
        $record = VerificationRateLimit::firstOrNew([
            'identifier' => $identifier,
            'type' => $type,
        ]);

        if (!$record->exists || $record->reset_at->isPast()) {
            $record->attempts = 1;
            $record->reset_at = now()->addHours(self::RESET_HOURS);
        } else {
            $record->attempts++;
        }

        $record->save();
    }
}