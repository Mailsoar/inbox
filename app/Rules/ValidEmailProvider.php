<?php

namespace App\Rules;

use App\Services\MxDetectionService;
use Illuminate\Contracts\Validation\Rule;

class ValidEmailProvider implements Rule
{
    private $reason;
    private $mxService;
    
    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        $this->mxService = new MxDetectionService();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->reason = 'Format d\'email invalide';
            return false;
        }
        
        $analysis = $this->mxService->analyzeEmail($value);
        
        if (isset($analysis['blocked']) && $analysis['blocked']) {
            $this->reason = $analysis['reason'] ?? 'Email non autorisé';
            return false;
        }
        
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->reason;
    }
}