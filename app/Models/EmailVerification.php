<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'ip_address',
        'session_token',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Générer un code de vérification
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Générer un token de session
     */
    public static function generateSessionToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Vérifier si le code est expiré
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Vérifier si le code est déjà utilisé
     */
    public function isUsed(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Marquer comme vérifié
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified_at' => now(),
            'session_token' => self::generateSessionToken(),
        ]);
    }
}