<?php

namespace App\Services\EmailServices;

use App\Models\EmailAccount;
use App\Services\OAuthTokenService;

abstract class BaseEmailService implements EmailServiceInterface
{
    protected EmailAccount $account;
    protected ?OAuthTokenService $tokenService = null;

    public function __construct(EmailAccount $account)
    {
        $this->account = $account;
        $this->tokenService = new OAuthTokenService();
    }

    /**
     * Ensure the OAuth token is valid before making requests
     */
    protected function ensureValidToken(): void
    {
        if (in_array($this->account->provider, ['gmail', 'outlook'])) {
            // For Microsoft accounts, use the comprehensive test and repair system
            if ($this->account->provider === 'outlook') {
                $this->tokenService->testAndRepairConnection($this->account);
                // Refresh account data after potential updates
                $this->account->refresh();
            } else {
                // For Gmail, use the standard token validation
                $this->tokenService->ensureValidToken($this->account);
            }
        }
    }

    /**
     * Get the access token
     */
    protected function getAccessToken(): ?string
    {
        if (!$this->account->oauth_token) {
            return null;
        }

        try {
            return decrypt($this->account->oauth_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the password
     */
    protected function getPassword(): ?string
    {
        if (!$this->account->password) {
            return null;
        }

        try {
            return decrypt($this->account->password);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Default implementation for getRecentEmails
     */
    public function getRecentEmails(int $limit = 10): array
    {
        // Default implementation - should be overridden by child classes
        return [];
    }
    
    /**
     * Default implementation for getEmailsFromFolder
     */
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array
    {
        // Default implementation - should be overridden by child classes
        return [];
    }
}