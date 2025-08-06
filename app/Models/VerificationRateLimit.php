<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationRateLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'type',
        'attempts',
        'reset_at',
    ];

    protected $casts = [
        'reset_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public static function checkAndIncrement(string $type, string $identifier): array
    {
        $limit = $type === 'email' 
            ? config('mailsoar.rate_limit_per_email', 10)
            : config('mailsoar.rate_limit_per_ip', 20);

        try {
            $record = static::where('type', $type)
                ->where('identifier', $identifier)
                ->where('reset_at', '>', now())
                ->first();

            if (!$record) {
                try {
                    $record = static::create([
                        'type' => $type,
                        'identifier' => $identifier,
                        'reset_at' => now()->endOfDay(),
                        'attempts' => 0
                    ]);
                } catch (\Exception $e) {
                    $record = static::where('type', $type)
                        ->where('identifier', $identifier)
                        ->where('reset_at', '>', now())
                        ->first();
                    
                    if (!$record) {
                        throw $e;
                    }
                }
            }

            if ($record->attempts >= $limit) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'limit' => $limit,
                    'resets_at' => $record->reset_at->toIso8601String(),
                ];
            }

            $record->increment('attempts');

            $remaining = max(0, $limit - $record->attempts);

            return [
                'allowed' => true,
                'remaining' => $remaining,
                'limit' => $limit,
                'resets_at' => $record->reset_at->toIso8601String(),
            ];
        } catch (\Exception $e) {
            return [
                'allowed' => true,
                'remaining' => 1,
                'limit' => $limit,
                'resets_at' => now()->endOfDay()->toIso8601String(),
            ];
        }
    }

    public static function getRemaining(string $type, string $identifier): int
    {
        $limit = $type === 'email' 
            ? config('mailsoar.rate_limit_per_email', 10)
            : config('mailsoar.rate_limit_per_ip', 20);

        try {
            $record = static::where('type', $type)
                ->where('identifier', $identifier)
                ->where('reset_at', '>', now())
                ->first();

            return $record ? max(0, $limit - $record->attempts) : $limit;
        } catch (\Exception $e) {
            return 0;
        }
    }
}