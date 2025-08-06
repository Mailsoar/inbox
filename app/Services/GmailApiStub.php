<?php

namespace App\Services;

/**
 * Temporary stub for Gmail API when service files are missing
 */
class GmailApiStub
{
    const GMAIL_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';
    
    public static function createClient(array $token): \Google\Client
    {
        $client = new \Google\Client();
        $client->setApplicationName('MailSoar InboxPlacement');
        $client->setScopes([self::GMAIL_READONLY]);
        $client->setAccessToken($token);
        
        return $client;
    }
    
    public static function searchEmails(\Google\Client $client, string $query): array
    {
        // Use direct API calls with HTTP client
        $accessToken = $client->getAccessToken()['access_token'] ?? null;
        if (!$accessToken) {
            throw new \Exception('No access token available');
        }
        
        $url = 'https://www.googleapis.com/gmail/v1/users/me/messages?q=' . urlencode($query);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Gmail API error: HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        return $data['messages'] ?? [];
    }
    
    public static function getMessage(\Google\Client $client, string $messageId): array
    {
        $accessToken = $client->getAccessToken()['access_token'] ?? null;
        if (!$accessToken) {
            throw new \Exception('No access token available');
        }
        
        $url = 'https://www.googleapis.com/gmail/v1/users/me/messages/' . $messageId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Gmail API error: HTTP ' . $httpCode);
        }
        
        return json_decode($response, true);
    }
}