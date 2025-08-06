<?php

namespace App\Models;

/**
 * @deprecated Use VerificationRateLimit instead
 * Temporary alias for backward compatibility
 */
class RateLimit extends VerificationRateLimit
{
    // Force this alias to use the verification_rate_limits table
    protected $table = 'verification_rate_limits';
}