<?php

namespace App\Services\EmailServices;

use Webklex\PHPIMAP\ClientManager;
use Illuminate\Support\Facades\Log;

class YahooService extends BaseEmailService
{
    private $client;

    /**
     * Test the connection to Yahoo
     */
    public function testConnection(): array
    {
        try {
            $this->connect();
            
            // Test getting folders
            $folders = $this->client->getFolders();
            
            $this->disconnect();
            
            return [
                'success' => true,
                'details' => [
                    'folders_count' => count($folders),
                    'folders' => array_map(fn($f) => $f->name, $folders->toArray())
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search for emails by unique ID
     */
    public function searchByUniqueId(string $uniqueId, $test = null): array
    {
        try {
            $this->connect();
            
            $results = [];
            
            \Log::info('Yahoo searchByUniqueId: Starting search', [
                'account' => $this->account->email,
                'uniqueId' => $uniqueId,
                'test_provided' => $test !== null
            ]);
            
            // Build folders to search using database mappings
            $foldersToSearch = [];
            
            // Add mapped inbox folder
            $mappedInbox = $this->account->getInboxFolder();
            if ($mappedInbox) {
                $foldersToSearch['inbox'] = $mappedInbox;
            }
            
            // Add mapped spam folder  
            $mappedSpam = $this->account->getSpamFolder();
            if ($mappedSpam) {
                $foldersToSearch['spam'] = $mappedSpam;
            }
            
            // Add additional inbox folders
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                $foldersToSearch['inbox'] = $additionalInbox->folder_name;
            }
            
            \Log::info('Yahoo searchByUniqueId: Folders to search', [
                'account' => $this->account->email,
                'folders' => $foldersToSearch
            ]);
            
            // If no mappings found, use default Yahoo folders
            if (empty($foldersToSearch)) {
                \Log::warning('No folder mappings found for Yahoo account, using defaults', [
                    'account' => $this->account->email
                ]);
                $foldersToSearch = [
                    'inbox' => 'Inbox',
                    'spam' => 'Bulk'
                ];
            }
            
            // Search in each folder
            foreach ($foldersToSearch as $placement => $folderName) {
                // Pass the test to searchInFolder if available
                $folderResults = $this->searchInFolder($folderName, $uniqueId, $placement, $test);
                if (!empty($folderResults)) {
                    $results = array_merge($results, $folderResults);
                    break; // Stop on first match for performance
                }
            }
            
            $this->disconnect();
            
            return $results;
            
        } catch (\Exception $e) {
            \Log::error('Yahoo searchByUniqueId error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get email details
     */
    public function getEmail(string $messageId): ?array
    {
        try {
            $this->connect();
            
            $inboxFolder = $this->account->getInboxFolder() ?? 'Inbox';
            
            try {
                $folder = $this->client->getFolder($inboxFolder);
                if ($folder) {
                    $message = $folder->messages()->getMessageByUid($messageId);
                    
                    if ($message) {
                        $email = $this->parseMessage($message);
                        $this->disconnect();
                        return $email;
                    }
                }
            } catch (\Exception $e) {
                \Log::debug("Yahoo: Could not get email from {$inboxFolder}: " . $e->getMessage());
            }
            
            $this->disconnect();
            return null;
        } catch (\Exception $e) {
            \Log::error('Yahoo getEmail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get raw headers
     */
    public function getRawHeaders(string $messageId, string $folderName = null): ?string
    {
        try {
            $this->connect();
            
            // If folder is specified, try that first
            if ($folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if ($folder) {
                        $message = $folder->messages()->getMessageByUid($messageId);
                        if ($message) {
                            $headers = $message->getHeader()->raw;
                            $this->disconnect();
                            return $headers;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to search in other folders
                }
            }
            
            // Try all available folders
            $allFolders = $this->client->getFolders();
            
            foreach ($allFolders as $folder) {
                try {
                    $message = $folder->messages()->getMessageByUid($messageId);
                    if ($message) {
                        $headers = $message->getHeader()->raw;
                        $this->disconnect();
                        return $headers;
                    }
                } catch (\Exception $e) {
                    // Continue to next folder
                }
            }
            
            $this->disconnect();
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get recent emails from inbox
     */
    public function getRecentEmails(int $limit = 10): array
    {
        try {
            $this->connect();
            
            $emails = [];
            
            // Try different folder names for inbox (Yahoo uses "Inbox")
            $inboxNames = ['Inbox', 'INBOX', 'inbox'];
            $folder = null;
            
            foreach ($inboxNames as $folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if ($folder) {
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            if (!$folder) {
                // If no standard inbox found, get first folder
                $folders = $this->client->getFolders();
                if ($folders && count($folders) > 0) {
                    $folder = $folders->first();
                }
            }
            
            if ($folder) {
                // Get recent messages
                $messages = $folder->messages()
                    ->all()
                    ->limit($limit)
                    ->get();
                
                foreach ($messages as $message) {
                    try {
                        $date = $message->getDate();
                        if ($date) {
                            if (is_string($date)) {
                                $dateStr = $date;
                            } elseif ($date instanceof \DateTime || $date instanceof \DateTimeInterface) {
                                $dateStr = $date->format('Y-m-d H:i:s');
                            } else {
                                // Si c'est un objet Attribute de PHPIMAP
                                $dateStr = (string) $date;
                            }
                        } else {
                            $dateStr = null;
                        }
                        
                        $emails[] = [
                            'id' => $message->getUid(),
                            'subject' => (string) $message->getSubject(),
                            'date' => $dateStr,
                            'from' => $message->getFrom()[0]->mail ?? '',
                        ];
                    } catch (\Exception $e) {
                        // Skip this message if there's an error
                        continue;
                    }
                }
            }
            
            $this->disconnect();
            return $emails;
        } catch (\Exception $e) {
            \Log::error('Error getting recent emails: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get emails from specific folder
     */

    /**
     * Connect to Yahoo IMAP
     */
    private function connect(): void
    {
        if ($this->client) {
            return;
        }

        $config = [
            'host' => 'imap.mail.yahoo.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $this->account->email,
            'password' => $this->getPassword(),
            'protocol' => 'imap',
            'authentication' => 'NORMAL'
        ];

        $cm = new ClientManager();
        $this->client = $cm->make($config);
        $this->client->connect();
    }

    /**
     * Disconnect from IMAP
     */
    private function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * Parse IMAP message to array
     */
    private function parseMessage($message): array
    {
        // Handle date formatting
        $date = $message->getDate();
        $dateStr = null;
        
        if ($date) {
            if (is_string($date)) {
                $dateStr = $date;
            } elseif ($date instanceof \DateTime || $date instanceof \DateTimeInterface) {
                $dateStr = $date->format('Y-m-d H:i:s');
            } else {
                // For PHPIMAP Attribute objects, convert to string
                $dateStr = (string) $date;
            }
        }
        
        // Get subject safely
        $subject = '';
        try {
            $subjectObj = $message->getSubject();
            if (is_string($subjectObj)) {
                $subject = $subjectObj;
            } else {
                $subject = (string) $subjectObj;
            }
        } catch (\Exception $e) {
            $subject = '';
        }
        
        // Get from safely
        $from = '';
        try {
            $fromArray = $message->getFrom();
            if ($fromArray && is_array($fromArray) && count($fromArray) > 0) {
                $from = $fromArray[0]->mail ?? '';
            }
        } catch (\Exception $e) {
            $from = '';
        }
        
        // Get headers safely
        $headers = '';
        try {
            $headerObj = $message->getHeader();
            if ($headerObj && property_exists($headerObj, 'raw')) {
                $headers = $headerObj->raw;
            }
        } catch (\Exception $e) {
            $headers = '';
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
            Log::error('Yahoo getFolders error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get emails from specific folder
     */
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array
    {
        try {
            $this->connect();
            
            $emails = [];
            
            // Map common folder names to Yahoo folder names
            $folderMap = [
                'SPAM' => 'Bulk',
                'JUNK' => 'Bulk',
                'SENT' => 'Sent',
                'DRAFT' => 'Draft',
                'TRASH' => 'Trash',
                'INBOX' => 'Inbox'  // Yahoo uses "Inbox" not "INBOX"
            ];
            
            $actualFolderName = $folderMap[strtoupper($folderName)] ?? $folderName;
            
            try {
                $folder = $this->client->getFolder($actualFolderName);
                if ($folder) {
                    $messages = $folder->messages()
                        ->all()
                        ->limit($limit)
                        ->get();
                    
                    foreach ($messages as $message) {
                        $email = $this->parseMessage($message);
                        $email['id'] = $message->getUid();
                        $email['folder'] = $folderName;
                        
                        // Set placement based on folder
                        if (in_array($actualFolderName, ['Bulk', 'Spam', 'Junk'])) {
                            $email['placement'] = 'spam';
                        }
                        
                        $emails[] = $email;
                    }
                }
            } catch (\Exception $e) {
                Log::debug("Error accessing Yahoo folder {$actualFolderName}: " . $e->getMessage());
            }
            
            $this->disconnect();
            
            return $emails;
            
        } catch (\Exception $e) {
            Log::error('Yahoo getEmailsFromFolder error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search for emails in a specific folder with optimized strategy
     */
    private function searchInFolder(string $folderName, string $uniqueId, string $placement, $test = null): array
    {
        $emails = [];
        
        try {
            $folder = $this->client->getFolder($folderName);
            if (!$folder) {
                return [];
            }
            
            \Log::info("Yahoo: Starting search in {$folderName} for {$uniqueId}", [
                'folder_exists' => $folder !== null
            ]);
            
            // Get test to determine search window (like Microsoft)
            if (!$test) {
                $test = \App\Models\Test::where('unique_id', $uniqueId)->first();
            }
            if ($test) {
                // Search from 1 hour before test creation, but not more than 7 days ago
                // This covers emails sent slightly before or after test creation
                $searchFrom = max(
                    $test->created_at->subHour(),
                    now()->subDays(7)
                );
                \Log::info("Yahoo: Using test-based search window", [
                    'test_created' => $test->created_at->format('Y-m-d H:i:s'),
                    'search_from' => $searchFrom->format('Y-m-d H:i:s')
                ]);
            } else {
                // Fallback to 3 days if test not found
                $searchFrom = now()->subDays(3);
                \Log::info("Yahoo: Test not found, using default 3 days");
            }
            
            try {
                // Use date filter like Microsoft
                // But also check if we need to search ALL recent messages
                if ($test && $test->created_at->diffInMinutes(now()) < 60) {
                    // For very recent tests, get all messages from last 2 hours
                    $messages = $folder->messages()
                        ->since(now()->subHours(2))
                        ->limit(50)
                        ->get();
                } else {
                    $messages = $folder->messages()
                        ->since($searchFrom)
                        ->limit(50) // Increased from 10 to 50 for better coverage
                        ->get();
                }
                    
                \Log::info("Yahoo: Retrieved messages from {$folderName}", [
                    'count' => count($messages),
                    'search_from' => $searchFrom->format('Y-m-d H:i:s')
                ]);
                    
                foreach ($messages as $message) {
                    // Quick check - only check subject first
                    try {
                        $subject = (string)$message->getSubject();
                        
                        // If found in subject, get full details
                        if (strpos($subject, $uniqueId) !== false) {
                            $email = $this->parseMessage($message);
                            $email['placement'] = $placement;
                            $email['folder'] = $folderName;
                            $emails[] = $email;
                            
                            \Log::info("Yahoo: Found email in {$folderName} - subject match");
                            return $emails; // Return immediately on first match
                        }
                        
                        // Only check body if we have time (optional)
                        $body = $message->getTextBody() ?: '';
                        if ($body && strpos($body, $uniqueId) !== false) {
                            $email = $this->parseMessage($message);
                            $email['placement'] = $placement;
                            $email['folder'] = $folderName;
                            $emails[] = $email;
                            
                            \Log::info("Yahoo: Found email in {$folderName} - body match");
                            return $emails; // Return immediately on first match
                        }
                    } catch (\Exception $e) {
                        // Skip problematic messages
                        continue;
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Yahoo: Error during quick search in {$folderName}: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            \Log::debug("Yahoo: Could not access folder {$folderName}: " . $e->getMessage());
        }
        
        return $emails;
    }
}