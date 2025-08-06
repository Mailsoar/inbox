<?php

use Illuminate\Support\Facades\Crypt;

if (!function_exists('encrypt')) {
    /**
     * Encrypt a value
     */
    function encrypt($value)
    {
        return Crypt::encryptString($value);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypt a value
     */
    function decrypt($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}