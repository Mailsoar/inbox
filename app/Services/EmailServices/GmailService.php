<?php

namespace App\Services\EmailServices;

use Google\Client;
use Illuminate\Support\Facades\Log;

class GmailService extends BaseEmailService
{
    /**
     * Search emails by unique ID
     */
    public function searchByUniqueId(string $uniqueId, $test = null): array
    {
        try {
            $this->ensureValidToken();
            $tokenString = $this->getAccessToken();
            
            if (!$tokenString) {
                throw new \Exception('No access token available');
            }
            
            // The token might be a JSON string or just the access token
            $accessToken = null;
            if (is_string($tokenString)) {
                // Try to decode as JSON first
                $decoded = json_decode($tokenString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['access_token'])) {
                    $accessToken = $decoded['access_token'];
                } else {
                    // It's probably just the token string
                    $accessToken = $tokenString;
                }
            }
            
            if (!$accessToken) {
                return [];
            }
            
            $emails = [];
            
            // Build search query based on mapped folders only
            // Gmail uses labels, so we need to translate folder names to label queries
            $labelQueries = [];
            
            // Get mapped folders from database
            $mappedFolders = $this->account->folderMappings()
                ->whereIn('folder_type', ['inbox', 'spam', 'additional_inbox'])
                ->get();
            
            if ($mappedFolders->isEmpty()) {
                // No mappings - don't search
                error_log("No folder mappings for Gmail account: {$this->account->email}");
                return [];
            }
            
            // Build label query based on mapped folders
            foreach ($mappedFolders as $mapping) {
                $folderName = $mapping->folder_name;
                
                // Translate folder names to Gmail labels
                if (stripos($folderName, 'SPAM') !== false) {
                    $labelQueries[] = 'in:spam';
                } elseif (stripos($folderName, 'Updates') !== false) {
                    $labelQueries[] = 'category:updates';
                } elseif (stripos($folderName, 'Promotions') !== false) {
                    $labelQueries[] = 'category:promotions';
                } elseif (stripos($folderName, 'Social') !== false) {
                    $labelQueries[] = 'category:social';
                } elseif (stripos($folderName, 'Forums') !== false) {
                    $labelQueries[] = 'category:forums';
                } elseif (stripos($folderName, 'INBOX') !== false && !stripos($folderName, '(')) {
                    // Plain INBOX (not a category)
                    $labelQueries[] = 'in:inbox';
                }
            }
            
            // Combine with unique ID search
            if (empty($labelQueries)) {
                return [];
            }
            
            // Search in all mapped labels/folders
            $labelQuery = '(' . implode(' OR ', $labelQueries) . ')';
            // Try searching in subject and body explicitly
            $query = '(subject:' . $uniqueId . ' OR ' . $uniqueId . ') ' . $labelQuery;
            
            
            $url = 'https://www.googleapis.com/gmail/v1/users/me/messages?q=' . urlencode($query) . '&maxResults=50';
            
            
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
                Log::error("Gmail API error for {$this->account->email}", [
                    'httpCode' => $httpCode,
                    'response' => substr($response, 0, 500)
                ]);
                return [];
            }
            
            $data = json_decode($response, true);
            $messages = $data['messages'] ?? [];
            
            
            foreach ($messages as $message) {
                $messageId = $message['id'];
                
                // Get full message
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
                
                if ($httpCode === 200) {
                    $fullMessage = json_decode($response, true);
                    $email = $this->parseGmailMessage($fullMessage);
                    
                    // Determine placement based on labels
                    $labels = $fullMessage['labelIds'] ?? [];
                    
                    if (strpos($this->account->email, 'pgaliegue33') !== false) {
                        error_log("Gmail message labels for {$this->account->email}: " . implode(', ', $labels));
                    }
                    
                    if (in_array('SPAM', $labels)) {
                        $email['placement'] = 'spam';
                    } elseif (in_array('CATEGORY_PROMOTIONS', $labels)) {
                        $email['placement'] = 'promotions';
                    } elseif (in_array('CATEGORY_UPDATES', $labels)) {
                        $email['placement'] = 'updates';
                    } elseif (in_array('CATEGORY_FORUMS', $labels)) {
                        $email['placement'] = 'forums';
                    } elseif (in_array('CATEGORY_SOCIAL', $labels)) {
                        $email['placement'] = 'social';
                    } else {
                        $email['placement'] = 'inbox';
                    }
                    
                    $emails[] = $email;
                }
            }
            
            return $emails;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get email details
     */
    public function getEmail(string $messageId): ?array
    {
        try {
            $this->ensureValidToken();
            $tokenString = $this->getAccessToken();
            
            // The token might be a JSON string or just the access token
            $accessToken = null;
            if (is_string($tokenString)) {
                $decoded = json_decode($tokenString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['access_token'])) {
                    $accessToken = $decoded['access_token'];
                } else {
                    $accessToken = $tokenString;
                }
            }
            
            if (!$accessToken) {
                return null;
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
            
            if ($httpCode === 200) {
                $message = json_decode($response, true);
                return $this->parseGmailMessage($message);
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get raw headers
     */
    public function getRawHeaders(string $messageId): ?string
    {
        try {
            $this->ensureValidToken();
            $tokenString = $this->getAccessToken();
            
            // The token might be a JSON string or just the access token
            $accessToken = null;
            if (is_string($tokenString)) {
                $decoded = json_decode($tokenString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['access_token'])) {
                    $accessToken = $decoded['access_token'];
                } else {
                    $accessToken = $tokenString;
                }
            }
            
            if (!$accessToken) {
                return null;
            }
            
            $url = 'https://www.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '?format=RAW';
            
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
            
            if ($httpCode === 200) {
                $message = json_decode($response, true);
                $rawData = base64_decode(str_replace(['-', '_'], ['+', '/'], $message['raw'] ?? ''));
                
                // Extract headers from raw message
                $headerEnd = strpos($rawData, "\r\n\r\n");
                if ($headerEnd !== false) {
                    return substr($rawData, 0, $headerEnd);
                }
                
                return $rawData;
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse Gmail message to array
     */
    private function parseGmailMessage(array $message): array
    {
        $headers = $message['payload']['headers'] ?? [];
        $subject = '';
        $from = '';
        $date = '';
        $messageId = '';
        
        foreach ($headers as $header) {
            switch (strtolower($header['name'])) {
                case 'subject':
                    $subject = $header['value'];
                    break;
                case 'from':
                    $from = $header['value'];
                    break;
                case 'date':
                    $date = $header['value'];
                    break;
                case 'message-id':
                    $messageId = $header['value'];
                    break;
            }
        }
        
        // Get body
        $body = $this->extractBody($message['payload'] ?? []);
        
        // Get raw headers
        $rawHeaders = $this->extractRawHeaders($headers);
        
        return [
            'message_id' => $messageId,
            'subject' => $subject,
            'from' => $from,
            'date' => $date,
            'body' => $body['text'] ?? '',
            'html_body' => $body['html'] ?? '',
            'headers' => $rawHeaders,
            'folder' => 'INBOX',
            'has_attachments' => $this->hasAttachments($message['payload'] ?? [])
        ];
    }
    
    /**
     * Extract body from payload
     */
    private function extractBody(array $payload): array
    {
        $text = '';
        $html = '';
        
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if ($part['mimeType'] === 'text/plain' && isset($part['body']['data'])) {
                    $text = base64_decode(str_replace(['-', '_'], ['+', '/'], $part['body']['data']));
                } elseif ($part['mimeType'] === 'text/html' && isset($part['body']['data'])) {
                    $html = base64_decode(str_replace(['-', '_'], ['+', '/'], $part['body']['data']));
                }
            }
        } elseif (isset($payload['body']['data'])) {
            $content = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload['body']['data']));
            if ($payload['mimeType'] === 'text/html') {
                $html = $content;
            } else {
                $text = $content;
            }
        }
        
        return ['text' => $text, 'html' => $html];
    }
    
    /**
     * Extract raw headers string
     */
    private function extractRawHeaders(array $headers): string
    {
        $rawHeaders = '';
        foreach ($headers as $header) {
            $rawHeaders .= $header['name'] . ': ' . $header['value'] . "\r\n";
        }
        return $rawHeaders;
    }
    
    /**
     * Check if message has attachments
     */
    private function hasAttachments(array $payload): bool
    {
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if (isset($part['filename']) && !empty($part['filename'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Test the connection to Gmail
     */
    public function testConnection(): array
    {
        try {
            $this->ensureValidToken();
            $tokenString = $this->getAccessToken();
            
            if (!$tokenString) {
                return ['status' => false, 'message' => 'No access token'];
            }
            
            // The token might be a JSON string or just the access token
            $accessToken = null;
            if (is_string($tokenString)) {
                // Try to decode as JSON first
                $decoded = json_decode($tokenString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['access_token'])) {
                    $accessToken = $decoded['access_token'];
                } else {
                    // It's probably just the token string
                    $accessToken = $tokenString;
                }
            }
            
            if (!$accessToken) {
                return ['status' => false, 'message' => 'No access token'];
            }
            
            // Test API connection
            $url = 'https://www.googleapis.com/gmail/v1/users/me/profile';
            
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
            
            if ($httpCode === 200) {
                $profile = json_decode($response, true);
                return [
                    'status' => true,
                    'message' => 'Connected to Gmail',
                    'details' => [
                        'email' => $profile['emailAddress'] ?? '',
                        'messages' => $profile['messagesTotal'] ?? 0
                    ]
                ];
            }
            
            return ['status' => false, 'message' => 'Failed to connect to Gmail'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get emails from a specific folder/label
     */
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array
    {
        try {
            $this->ensureValidToken();
            $accessToken = $this->getAccessToken();
            
            if (!$accessToken) {
                Log::error('Gmail: No access token available for getEmailsFromFolder');
                return [];
            }
            
            // Map folder names to Gmail labels
            $labelMap = [
                'INBOX' => 'INBOX',
                'INBOX (Primary)' => 'CATEGORY_PERSONAL',
                'INBOX (Promotions)' => 'CATEGORY_PROMOTIONS',
                'INBOX (Social)' => 'CATEGORY_SOCIAL',
                'INBOX (Updates)' => 'CATEGORY_UPDATES',
                'INBOX (Forums)' => 'CATEGORY_FORUMS',
                'SPAM' => 'SPAM',
                'TRASH' => 'TRASH',
                'SENT' => 'SENT',
                'DRAFT' => 'DRAFT',
                'IMPORTANT' => 'IMPORTANT',
                'STARRED' => 'STARRED',
                'UNREAD' => 'UNREAD'
            ];
            
            $label = $labelMap[$folderName] ?? $folderName;
            
            Log::info('Gmail: Fetching emails from folder', [
                'folder_name' => $folderName,
                'label' => $label,
                'limit' => $limit
            ]);
            
            // Build query based on label
            $query = '';
            if ($label === 'INBOX') {
                $query = 'in:inbox';
            } elseif ($label === 'SPAM') {
                $query = 'in:spam';
            } elseif ($label === 'TRASH') {
                $query = 'in:trash';
            } elseif ($label === 'SENT') {
                $query = 'in:sent';
            } elseif ($label === 'DRAFT') {
                $query = 'in:drafts';
            } elseif (strpos($label, 'CATEGORY_') === 0) {
                $query = 'category:' . strtolower(str_replace('CATEGORY_', '', $label));
            } else {
                $query = 'label:' . $label;
            }
            
            // Get message list
            $url = 'https://www.googleapis.com/gmail/v1/users/me/messages?' . http_build_query([
                'q' => $query,
                'maxResults' => $limit
            ]);
            
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
                Log::error('Gmail: Failed to fetch messages', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return [];
            }
            
            $data = json_decode($response, true);
            $messages = $data['messages'] ?? [];
            
            Log::info('Gmail: Messages found', [
                'count' => count($messages),
                'folder' => $folderName
            ]);
            
            $emails = [];
            
            // Fetch details for each message
            foreach ($messages as $msg) {
                try {
                    $messageId = $msg['id'];
                    
                    // Get full message details
                    $url = "https://www.googleapis.com/gmail/v1/users/me/messages/{$messageId}";
                    
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
                    
                    if ($httpCode === 200) {
                        $message = json_decode($response, true);
                        $parsed = $this->parseGmailMessage($message);
                        $parsed['id'] = $messageId;
                        $parsed['uid'] = $messageId;
                        $parsed['folder'] = $folderName;
                        
                        // Get raw headers if possible
                        $headers = $this->extractHeadersFromGmailMessage($message);
                        $parsed['headers'] = $headers;
                        
                        $emails[] = $parsed;
                        
                        Log::info('Gmail: Email parsed', [
                            'id' => $messageId,
                            'subject' => substr($parsed['subject'] ?? '', 0, 50),
                            'has_headers' => !empty($headers)
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Gmail: Error parsing message', [
                        'message_id' => $messageId ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return $emails;
            
        } catch (\Exception $e) {
            Log::error('Gmail: Error in getEmailsFromFolder', [
                'folder' => $folderName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Extract headers from Gmail message
     */
    private function extractHeadersFromGmailMessage(array $message): string
    {
        $headers = [];
        $payload = $message['payload'] ?? [];
        $messageHeaders = $payload['headers'] ?? [];
        
        foreach ($messageHeaders as $header) {
            $name = $header['name'] ?? '';
            $value = $header['value'] ?? '';
            if ($name) {
                $headers[] = "{$name}: {$value}";
            }
        }
        
        return implode("\r\n", $headers);
    }
}