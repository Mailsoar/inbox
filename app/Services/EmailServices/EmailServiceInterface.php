<?php

namespace App\Services\EmailServices;

interface EmailServiceInterface
{
    /**
     * Test the connection to the email account
     */
    public function testConnection(): array;
    
    /**
     * Search for emails by unique ID
     */
    public function searchByUniqueId(string $uniqueId, $test = null): array;
    
    /**
     * Get email details
     */
    public function getEmail(string $messageId): ?array;
    
    /**
     * Get raw headers
     */
    public function getRawHeaders(string $messageId): ?string;
    
    /**
     * Get recent emails from inbox
     */
    public function getRecentEmails(int $limit = 10): array;
    
    /**
     * Get emails from specific folder
     */
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array;
}