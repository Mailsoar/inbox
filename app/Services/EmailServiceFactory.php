<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Services\EmailServices\GmailService;
use App\Services\EmailServices\OutlookService;
use App\Services\EmailServices\YahooService;
use App\Services\EmailServices\GenericImapService;
use App\Services\EmailServices\EmailServiceInterface;

class EmailServiceFactory
{
    /**
     * Create an email service instance based on the provider
     */
    public static function make(EmailAccount $account): EmailServiceInterface
    {
        switch ($account->provider) {
            case 'gmail':
                // Use API for Gmail (IMAP OAuth2 is complex)
                // But the service only searches in mapped folders
                return new GmailService($account);
                
            case 'outlook':
                return self::makeOutlookService($account);
                
            case 'yahoo':
                return new YahooService($account);
                
            case 'imap':
            case 'generic_imap':  // Backward compatibility
                return new GenericImapService($account);
                
            default:
                throw new \Exception("Unknown email provider: {$account->provider}");
        }
    }
    
    /**
     * Determine which Outlook service to use based on the account domain
     */
    private static function makeOutlookService(EmailAccount $account): EmailServiceInterface
    {
        // Always use IMAP for Outlook (Graph API has delays)
        return new OutlookService($account);
    }
}