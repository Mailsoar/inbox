<?php

namespace App\Services\EmailServices;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;

class OutlookService extends BaseEmailService
{
    protected Client $client;
    
    /**
     * Test the connection
     */
    public function testConnection(): array
    {
        try {
            $this->connect();
            
            // Get folders to verify connection
            $folders = $this->client->getFolders();
            $folderCount = count($folders);
            
            $this->disconnect();
            
            return [
                'success' => true,
                'message' => "Connexion réussie. {$folderCount} dossiers trouvés.",
                'folders' => $folderCount
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Connect to Outlook via IMAP
     */
    protected function connect(): void
    {
        $config = [
            'host' => 'outlook.office365.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $this->account->email,
            'password' => '', // Will be set based on auth type
            'protocol' => 'imap',
            'authentication' => null,
        ];
        
        // Use OAuth token if available, fallback to password
        if ($this->account->oauth_token) {
            try {
                $this->ensureValidToken();
                $config['password'] = decrypt($this->account->oauth_token);
                $config['authentication'] = 'oauth';
            } catch (\Exception $e) {
                \Log::warning('OAuth token failed for Outlook account, trying password fallback', [
                    'account' => $this->account->email,
                    'error' => $e->getMessage()
                ]);
                
                if (!$this->account->password) {
                    throw new \Exception('No valid authentication method for Outlook account');
                }
                
                $config['password'] = decrypt($this->account->password);
                $config['authentication'] = 'login';
            }
        } elseif ($this->account->password) {
            // Use password authentication (app password)
            $config['password'] = decrypt($this->account->password);
            $config['authentication'] = 'login';
        } else {
            throw new \Exception('No authentication method configured for Outlook account');
        }
        
        \Log::info('Outlook: Attempting connection', [
            'account' => $this->account->email,
            'host' => $config['host'],
            'auth_method' => $config['authentication'],
            'has_password' => !empty($config['password'])
        ]);
        
        $cm = new ClientManager();
        $this->client = $cm->make($config);
        $this->client->connect();
        
        \Log::info('Outlook: Connection successful', [
            'account' => $this->account->email
        ]);
    }
    
    /**
     * Disconnect from server
     */
    protected function disconnect(): void
    {
        if (isset($this->client)) {
            $this->client->disconnect();
        }
    }
    
    /**
     * Get emails from Outlook
     */
    public function getEmails(int $limit = 50): array
    {
        try {
            $this->connect();
            
            $emails = [];
            // Try to use mapped inbox folder first, fallback to 'INBOX'
            $inboxFolder = $this->account->getInboxFolder() ?? 'INBOX';
            
            try {
                $inbox = $this->client->getFolder($inboxFolder);
            } catch (\Exception $e) {
                \Log::debug('Outlook: Could not access mapped inbox folder, trying INBOX', [
                    'mapped_folder' => $inboxFolder,
                    'error' => $e->getMessage()
                ]);
                $inbox = $this->client->getFolder('INBOX');
            }
            
            if ($inbox) {
                $messages = $inbox->messages()
                    ->all()
                    ->limit($limit)
                    ->get();
                
                foreach ($messages as $message) {
                    $emails[] = $this->parseImapMessage($message);
                }
            }
            
            $this->disconnect();
            
            return $emails;
        } catch (\Exception $e) {
            \Log::error('Outlook IMAP getEmails error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search for specific email
     */
    public function searchEmail(string $searchCriteria): ?array
    {
        try {
            $this->connect();
            
            // Build folders to search using ONLY database mappings (no fallbacks)
            $foldersToSearch = [];
            
            // Add mapped inbox folder
            $mappedInbox = $this->account->getInboxFolder();
            if ($mappedInbox) {
                $foldersToSearch[] = $mappedInbox;
            }
            
            // Add mapped spam folder
            $mappedSpam = $this->account->getSpamFolder();
            if ($mappedSpam) {
                $foldersToSearch[] = $mappedSpam;
            }
            
            // Add additional inbox folders
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                $foldersToSearch[] = $additionalInbox->folder_name;
            }
            
            // If no mappings found, don't search
            if (empty($foldersToSearch)) {
                \Log::warning('No folder mappings found for account', [
                    'account' => $this->account->email
                ]);
                return [];
            }
            
            foreach ($foldersToSearch as $folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if (!$folder) {
                        \Log::debug('Outlook: Folder not found', [
                            'folder' => $folderName,
                            'account' => $this->account->email
                        ]);
                        continue;
                    }
                    
                    \Log::debug('Outlook: Successfully accessed folder', [
                        'folder' => $folderName,
                        'account' => $this->account->email
                    ]);
                    
                    // Search by subject and body
                    $messages = $folder->messages()
                        ->text($searchCriteria)
                        ->get();
                    
                    if (count($messages) > 0) {
                        $message = $messages->first();
                        $result = $this->parseImapMessage($message);
                        $result['placement'] = $this->determinePlacement($folderName);
                        
                        $this->disconnect();
                        return $result;
                    }
                } catch (\Exception $e) {
                    \Log::debug("Error searching folder {$folderName}: " . $e->getMessage());
                    continue;
                }
            }
            
            $this->disconnect();
            return null;
            
        } catch (\Exception $e) {
            \Log::error('Outlook IMAP search error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available folders
     */
    public function getFolders(): array
    {
        try {
            $this->connect();
            
            $folders = [];
            $imapFolders = $this->client->getFolders();
            
            foreach ($imapFolders as $folder) {
                $folders[] = [
                    'name' => $folder->name,
                    'full_name' => $folder->full_name,
                    'messages' => $folder->messages()->all()->count()
                ];
            }
            
            $this->disconnect();
            
            return $folders;
        } catch (\Exception $e) {
            \Log::error('Outlook IMAP getFolders error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Parse IMAP message
     */
    private function parseImapMessage($message): array
    {
        try {
            // Get date safely
            $date = $message->getDate();
            $dateStr = null;
            
            if ($date) {
                if (is_string($date)) {
                    $dateStr = $date;
                } elseif ($date instanceof \DateTime || $date instanceof \DateTimeInterface) {
                    $dateStr = $date->format('Y-m-d H:i:s');
                } else {
                    // For PHPIMAP Attribute objects
                    $dateStr = (string) $date;
                }
            }
            
            // Get from safely
            $from = '';
            try {
                $fromArray = $message->getFrom();
                if ($fromArray) {
                    // Check if it's an array or collection
                    if (is_array($fromArray)) {
                        if (count($fromArray) > 0) {
                            $from = $fromArray[0]->mail ?? '';
                        }
                    } elseif (is_object($fromArray)) {
                        // If it's an object, try to get the first item
                        if (method_exists($fromArray, 'first')) {
                            $firstFrom = $fromArray->first();
                            if ($firstFrom && isset($firstFrom->mail)) {
                                $from = $firstFrom->mail;
                            }
                        } elseif (isset($fromArray->mail)) {
                            // Direct access if it's a single object
                            $from = $fromArray->mail;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::debug('Could not get from address: ' . $e->getMessage());
            }
            
            // Get subject safely
            $subject = '';
            try {
                $subjectObj = $message->getSubject();
                $subject = (string) $subjectObj;
            } catch (\Exception $e) {
                \Log::debug('Could not get subject: ' . $e->getMessage());
            }
            
            // Get headers safely
            $headers = '';
            try {
                $headerObj = $message->getHeader();
                if ($headerObj && property_exists($headerObj, 'raw')) {
                    $headers = $headerObj->raw;
                }
            } catch (\Exception $e) {
                \Log::debug('Could not get headers: ' . $e->getMessage());
            }
            
            return [
                'uid' => $message->getUid(),
                'subject' => $subject,
                'from' => $from,
                'date' => $dateStr,
                'placement' => 'inbox',
                'headers' => $headers,
                'body' => $message->getTextBody() ?: '',
                'html_body' => $message->getHTMLBody() ?: '',
            ];
        } catch (\Exception $e) {
            \Log::error('Error in parseImapMessage: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Determine placement based on folder
     */
    private function determinePlacement(string $folder): string
    {
        // First check against database folder mappings
        try {
            // Check if this is the mapped spam folder
            $spamFolder = $this->account->getSpamFolder();
            if ($spamFolder && $folder === $spamFolder) {
                \Log::debug('Outlook: Folder identified as spam via database mapping', [
                    'folder' => $folder,
                    'mapped_spam_folder' => $spamFolder
                ]);
                return 'spam';
            }
            
            // Check if this is the mapped inbox folder
            $inboxFolder = $this->account->getInboxFolder();
            if ($inboxFolder && $folder === $inboxFolder) {
                \Log::debug('Outlook: Folder identified as inbox via database mapping', [
                    'folder' => $folder,
                    'mapped_inbox_folder' => $inboxFolder
                ]);
                return 'inbox';
            }
            
            // Check if this is an additional inbox folder
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                if ($folder === $additionalInbox->folder_name) {
                    \Log::debug('Outlook: Folder identified as additional inbox via database mapping', [
                        'folder' => $folder,
                        'mapped_additional_inbox' => $additionalInbox->folder_name
                    ]);
                    return 'inbox';
                }
            }
        } catch (\Exception $e) {
            \Log::debug('Outlook: Error checking database folder mappings, falling back to pattern matching', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ]);
        }
        
        // Fallback to pattern matching if no database mapping found
        $folderLower = strtolower($folder);
        
        if (in_array($folderLower, ['junk', 'junk email', 'spam', 'bulk'])) {
            \Log::debug('Outlook: Folder identified as spam via pattern matching', [
                'folder' => $folder
            ]);
            return 'spam';
        }
        
        \Log::debug('Outlook: Folder defaulting to inbox placement', [
            'folder' => $folder
        ]);
        return 'inbox';
    }
    
    /**
     * Search for emails by unique ID
     */
    public function searchByUniqueId(string $uniqueId, $test = null): array
    {
        try {
            $this->connect();
            
            $results = [];
            
            // Determine search window based on test age
            // Use passed test or fetch it from unique ID
            if (!$test) {
                $test = \App\Models\Test::where('unique_id', $uniqueId)->first();
            }
            if ($test) {
                // For very recent tests (less than 60 minutes old), search last 2 hours
                // This handles cases where email is sent shortly after test creation
                if ($test->created_at->diffInMinutes(now()) < 60) {
                    $searchFrom = now()->subHours(2);
                    \Log::info('Outlook searchByUniqueId: Recent test, using 2 hour window', [
                        'test_created' => $test->created_at->format('Y-m-d H:i:s'),
                        'search_from' => $searchFrom->format('Y-m-d H:i:s')
                    ]);
                } else {
                    // Search from 1 hour before test creation, but not more than 7 days ago
                    $searchFrom = max(
                        $test->created_at->subHour(),
                        now()->subDays(7)
                    );
                    \Log::info('Outlook searchByUniqueId: Using test-based search window', [
                        'test_created' => $test->created_at->format('Y-m-d H:i:s'),
                        'search_from' => $searchFrom->format('Y-m-d H:i:s'),
                        'test_age_hours' => $test->created_at->diffInHours(now())
                    ]);
                }
            } else {
                // Fallback to 3 days if test not found
                $searchFrom = now()->subDays(3);
                \Log::info('Outlook searchByUniqueId: Test not found, using default 3 days');
            }
            
            \Log::info('Outlook searchByUniqueId: Starting search', [
                'account' => $this->account->email,
                'uniqueId' => $uniqueId,
                'search_from' => $searchFrom->format('Y-m-d H:i:s')
            ]);
            
            // Build folders to search using ONLY database mappings (no fallbacks)
            $foldersToSearch = [];
            
            // Add mapped inbox folder
            $mappedInbox = $this->account->getInboxFolder();
            if ($mappedInbox) {
                $foldersToSearch[] = $mappedInbox;
            }
            
            // Add mapped spam folder
            $mappedSpam = $this->account->getSpamFolder();
            if ($mappedSpam) {
                $foldersToSearch[] = $mappedSpam;
            }
            
            // Add additional inbox folders
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                $foldersToSearch[] = $additionalInbox->folder_name;
            }
            
            \Log::info('Outlook searchByUniqueId: Folders to search', [
                'account' => $this->account->email,
                'folders' => $foldersToSearch
            ]);
            
            // If no mappings found, don't search
            if (empty($foldersToSearch)) {
                \Log::warning('No folder mappings found for account', [
                    'account' => $this->account->email
                ]);
                return [];
            }
            
            foreach ($foldersToSearch as $folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if (!$folder) {
                        \Log::debug('Outlook: Folder not found', [
                            'folder' => $folderName,
                            'account' => $this->account->email
                        ]);
                        continue;
                    }
                    
                    \Log::debug('Outlook: Successfully accessed folder', [
                        'folder' => $folderName,
                        'account' => $this->account->email
                    ]);
                    
                    // For Outlook, skip IMAP search and go directly to optimized search
                    // IMAP search is unreliable and slow for Outlook
                    \Log::debug("Outlook: Using optimized search for {$folderName}");
                    
                    try {
                        // Use the calculated search window
                        $messages = $folder->messages()
                            ->since($searchFrom)
                            ->limit(200) // Increased limit to 200 for better coverage
                            ->get();
                        
                        \Log::debug('Outlook: Got messages from folder', [
                            'folder' => $folderName,
                            'account' => $this->account->email,
                            'message_count' => count($messages),
                            'uniqueId' => $uniqueId
                        ]);
                        
                        foreach ($messages as $message) {
                            try {
                                // Quick check - subject first
                                $subject = (string)$message->getSubject();
                                
                                if (strpos($subject, $uniqueId) !== false) {
                                    $email = $this->parseImapMessage($message);
                                    $email['placement'] = $this->determinePlacement($folderName);
                                    $email['folder'] = $folderName;
                                    $results[] = $email;
                                    
                                    \Log::info("Outlook: Found email in {$folderName} - subject match");
                                    break; // Found in this folder, move to next folder
                                }
                                
                                // Check body only if not found in subject
                                $body = $message->getTextBody() ?: '';
                                if ($body && strpos($body, $uniqueId) !== false) {
                                    $email = $this->parseImapMessage($message);
                                    $email['placement'] = $this->determinePlacement($folderName);
                                    $email['folder'] = $folderName;
                                    $results[] = $email;
                                    
                                    \Log::info("Outlook: Found email in {$folderName} - body match");
                                    break; // Found in this folder, move to next folder
                                }
                            } catch (\Exception $e) {
                                // Skip problematic messages
                                continue;
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::debug("Outlook: Error during optimized search in {$folderName}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    \Log::debug("Error searching folder {$folderName}: " . $e->getMessage());
                    continue;
                }
            }
            
            $this->disconnect();
            return $results;
            
        } catch (\Exception $e) {
            \Log::error('Outlook searchByUniqueId error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get email details by message ID
     */
    public function getEmail(string $messageId): ?array
    {
        try {
            $this->connect();
            
            // Build folders to search using ONLY database mappings (no fallbacks)
            $foldersToSearch = [];
            
            // Add mapped inbox folder
            $mappedInbox = $this->account->getInboxFolder();
            if ($mappedInbox) {
                $foldersToSearch[] = $mappedInbox;
            }
            
            // Add mapped spam folder
            $mappedSpam = $this->account->getSpamFolder();
            if ($mappedSpam) {
                $foldersToSearch[] = $mappedSpam;
            }
            
            // Add additional inbox folders
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                $foldersToSearch[] = $additionalInbox->folder_name;
            }
            
            // If no mappings found, don't search
            if (empty($foldersToSearch)) {
                \Log::warning('No folder mappings found for account', [
                    'account' => $this->account->email
                ]);
                return [];
            }
            
            foreach ($foldersToSearch as $folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if (!$folder) {
                        \Log::debug('Outlook: Folder not found', [
                            'folder' => $folderName,
                            'account' => $this->account->email
                        ]);
                        continue;
                    }
                    
                    \Log::debug('Outlook: Successfully accessed folder', [
                        'folder' => $folderName,
                        'account' => $this->account->email
                    ]);
                    
                    $messages = $folder->messages()->all()->get();
                    
                    foreach ($messages as $message) {
                        if ($message->getMessageId() === $messageId || 
                            $message->getUid() === $messageId) {
                            $result = $this->parseImapMessage($message);
                            $result['placement'] = $this->determinePlacement($folderName);
                            
                            $this->disconnect();
                            return $result;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            $this->disconnect();
            return null;
            
        } catch (\Exception $e) {
            \Log::error('Outlook getEmail error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get raw headers for a message
     */
    public function getRawHeaders(string $messageId): ?string
    {
        $email = $this->getEmail($messageId);
        return $email ? ($email['headers'] ?? null) : null;
    }
    
    /**
     * Get recent emails from inbox
     */
    public function getRecentEmails(int $limit = 10): array
    {
        return $this->getEmails($limit);
    }
    
    /**
     * Get emails from specific folder
     */
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array
    {
        try {
            $this->connect();
            
            $emails = [];
            
            \Log::info('Outlook: Looking for folder', [
                'folder_name' => $folderName,
                'account' => $this->account->email
            ]);
            
            // Try to get the folder
            try {
                $folder = $this->client->getFolder($folderName);
                
                if ($folder) {
                    \Log::info('Outlook: Folder found', [
                        'folder_name' => $folderName,
                        'folder_exists' => true
                    ]);
                    
                    // Get recent messages sorted by date (newest first)
                    // Use since() to get only recent emails (last 30 days)
                    $sinceDate = now()->subDays(30);
                    $messages = $folder->messages()
                        ->since($sinceDate)
                        ->limit($limit)
                        ->get()
                        ->sortByDesc(function($message) {
                            return $message->getDate();
                        });
                    
                    \Log::info('Outlook: Messages retrieved', [
                        'folder_name' => $folderName,
                        'message_count' => count($messages)
                    ]);
                    
                    foreach ($messages as $message) {
                        try {
                            $email = $this->parseImapMessage($message);
                            $email['id'] = $email['uid']; // Ensure 'id' field exists
                            $email['placement'] = $this->determinePlacement($folderName);
                            $email['folder'] = $folderName;
                            $emails[] = $email;
                            
                            \Log::info('Outlook: Email parsed successfully', [
                                'uid' => $email['uid'],
                                'subject' => substr($email['subject'] ?? '', 0, 50),
                                'has_headers' => !empty($email['headers'])
                            ]);
                        } catch (\Exception $parseError) {
                            \Log::error('Outlook: Error parsing message', [
                                'error' => $parseError->getMessage(),
                                'message_uid' => method_exists($message, 'getUid') ? $message->getUid() : 'unknown'
                            ]);
                            continue;
                        }
                    }
                }
            } catch (\Exception $folderError) {
                \Log::warning('Outlook: Could not access folder', [
                    'folder_name' => $folderName,
                    'error' => $folderError->getMessage()
                ]);
                
                // Try alternative folder names for Outlook
                $alternativeFolders = [
                    'INBOX' => ['Inbox', 'INBOX'],
                    'Junk Email' => ['Junk Email', 'Junk', 'Spam', 'Bulk Mail'],
                    'Junk' => ['Junk Email', 'Junk', 'Spam'],
                    'Spam' => ['Junk Email', 'Junk', 'Spam']
                ];
                
                if (isset($alternativeFolders[$folderName])) {
                    foreach ($alternativeFolders[$folderName] as $altFolder) {
                        try {
                            $folder = $this->client->getFolder($altFolder);
                            if ($folder) {
                                \Log::info('Outlook: Using alternative folder', [
                                    'original' => $folderName,
                                    'alternative' => $altFolder
                                ]);
                                
                                $messages = $folder->messages()
                                    ->all()
                                    ->limit($limit)
                                    ->get();
                                
                                foreach ($messages as $message) {
                                    try {
                                        $email = $this->parseImapMessage($message);
                                        $email['id'] = $email['uid'];
                                        $email['placement'] = $this->determinePlacement($altFolder);
                                        $email['folder'] = $folderName;
                                        $emails[] = $email;
                                        
                                        \Log::info('Outlook: Email parsed successfully (alt folder)', [
                                            'uid' => $email['uid'],
                                            'subject' => substr($email['subject'] ?? '', 0, 50),
                                            'has_headers' => !empty($email['headers'])
                                        ]);
                                    } catch (\Exception $parseError) {
                                        \Log::error('Outlook: Error parsing message (alt folder)', [
                                            'error' => $parseError->getMessage()
                                        ]);
                                        continue;
                                    }
                                }
                                break;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }
            
            $this->disconnect();
            
            return $emails;
        } catch (\Exception $e) {
            \Log::error('Outlook getEmailsFromFolder error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Fallback search when IMAP search fails (DEPRECATED - kept for compatibility)
     */
    private function fallbackSearch($folder, string $uniqueId, string $folderName, array &$results): void
    {
        try {
            \Log::info("Outlook: Starting fallback search in {$folderName} for {$uniqueId}");
            
            // This method is now deprecated as we use optimized search directly
            // Keeping minimal implementation for compatibility
            $messages = $folder->messages()
                ->all()
                ->limit(10) // Very limited
                ->get();
                
            $foundCount = 0;
            foreach ($messages as $message) {
                try {
                    // Get message content
                    $subject = (string)$message->getSubject();
                    $body = $message->getTextBody() ?: '';
                    $htmlBody = $message->getHTMLBody() ?: '';
                    
                    // Check if unique ID is present
                    if (strpos($subject, $uniqueId) !== false || 
                        strpos($body, $uniqueId) !== false ||
                        strpos($htmlBody, $uniqueId) !== false) {
                        
                        $email = $this->parseImapMessage($message);
                        $email['placement'] = $this->determinePlacement($folderName);
                        $email['folder'] = $folderName;
                        $results[] = $email;
                        $foundCount++;
                        
                        \Log::info("Outlook: Found email via fallback search", [
                            'folder' => $folderName,
                            'subject' => substr($subject, 0, 100),
                            'uniqueId' => $uniqueId
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::debug("Error processing message in fallback search: " . $e->getMessage());
                    continue;
                }
            }
            
            if ($foundCount === 0) {
                \Log::debug("Outlook: Fallback search found no results in {$folderName}");
            }
            
        } catch (\Exception $e) {
            \Log::error("Outlook: Fallback search failed in {$folderName}: " . $e->getMessage());
        }
    }
}