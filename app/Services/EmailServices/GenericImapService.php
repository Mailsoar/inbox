<?php

namespace App\Services\EmailServices;

use Webklex\PHPIMAP\ClientManager;

class GenericImapService extends BaseEmailService
{
    private $client;

    /**
     * Test the connection
     */
    public function testConnection(): array
    {
        try {
            $this->connect();
            
            // Get all folders recursively
            $allFolders = $this->getAllFoldersRecursively();
            
            $this->disconnect();
            
            return [
                'success' => true,
                'details' => [
                    'folders_count' => count($allFolders),
                    'folders' => $allFolders
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search for emails by unique ID
     */
    public function searchByUniqueId(string $uniqueId, $test = null): array
    {
        try {
            $startTime = microtime(true);
            \Log::debug("GenericImap: Starting search for {$uniqueId}");
            
            $this->connect();
            
            $emails = [];
            
            // First search in mapped folders only
            $inboxFolder = $this->account->getInboxFolder();
            $spamFolder = $this->account->getSpamFolder();
            
            // Search in INBOX first if mapped
            if ($inboxFolder) {
                try {
                    $folder = $this->client->getFolder($inboxFolder);
                    if ($folder) {
                        $messages = $folder->messages()
                            ->text($uniqueId)
                            ->limit(30)
                            ->get();
                            
                        foreach ($messages as $message) {
                            $email = $this->parseMessage($message);
                            $email['placement'] = 'inbox';
                            $emails[] = $email;
                        }
                        
                        if (!empty($emails)) {
                            $this->disconnect();
                            \Log::debug("GenericImap: Found in inbox, search took " . round(microtime(true) - $startTime, 2) . "s");
                            return $emails;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug("GenericImap: Could not search in inbox folder");
                }
            }
            
            // Search in spam folder if mapped and not found in inbox
            if ($spamFolder && empty($emails)) {
                try {
                    $folder = $this->client->getFolder($spamFolder);
                    if ($folder) {
                        $messages = $folder->messages()
                            ->text($uniqueId)
                            ->limit(30)
                            ->get();
                            
                        foreach ($messages as $message) {
                            $email = $this->parseMessage($message);
                            $email['placement'] = 'spam';
                            $emails[] = $email;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug("GenericImap: Could not search in spam folder");
                }
            }
            
            // If still not found and no folders mapped, fall back to recursive search (limited)
            if (empty($emails) && !$inboxFolder && !$spamFolder) {
                \Log::debug("GenericImap: No folders mapped, falling back to limited recursive search");
                $this->searchInFolderRecursively($this->client->getFolders(), $uniqueId, $emails, 20); // Limit to 20 messages per folder
            }
            
            $this->disconnect();
            \Log::debug("GenericImap: Total search took " . round(microtime(true) - $startTime, 2) . "s");
            
            return $emails;
        } catch (\Exception $e) {
            \Log::error("GenericImap: Search error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search for emails in folders recursively
     */
    private function searchInFolderRecursively($folders, string $uniqueId, array &$emails, int $limit = 50): void
    {
        foreach ($folders as $folder) {
            try {
                $messages = $folder->messages()
                    ->text($uniqueId)
                    ->limit($limit)
                    ->get();
                    
                foreach ($messages as $message) {
                    $email = $this->parseMessage($message);
                    
                    // Determine placement based on folder name and full path
                    $email['placement'] = $this->determinePlacement($folder);
                    
                    $emails[] = $email;
                }
            } catch (\Exception $e) {
                // Skip folders that can't be accessed
                \Log::debug('Could not search in folder', [
                    'folder' => $folder->name,
                    'error' => $e->getMessage()
                ]);
            }
            
            // If folder has children, search them too
            if ($folder->hasChildren()) {
                try {
                    $children = $folder->getChildren();
                    $this->searchInFolderRecursively($children, $uniqueId, $emails, $limit);
                } catch (\Exception $e) {
                    \Log::debug('Could not get children of folder', [
                        'folder' => $folder->name,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    /**
     * Determine email placement based on folder name
     */
    private function determinePlacement($folder): string
    {
        $folderName = $folder->name;
        $fullName = $folder->full_name ?? $folder->name;
        
        // First check against database folder mappings
        try {
            // Check if this is the mapped spam folder
            $spamFolder = $this->account->getSpamFolder();
            if ($spamFolder && ($folderName === $spamFolder || $fullName === $spamFolder)) {
                \Log::debug('Folder identified as spam via database mapping', [
                    'folder' => $folderName,
                    'mapped_spam_folder' => $spamFolder
                ]);
                return 'spam';
            }
            
            // Check if this is the mapped inbox folder
            $inboxFolder = $this->account->getInboxFolder();
            if ($inboxFolder && ($folderName === $inboxFolder || $fullName === $inboxFolder)) {
                \Log::debug('Folder identified as inbox via database mapping', [
                    'folder' => $folderName,
                    'mapped_inbox_folder' => $inboxFolder
                ]);
                return 'inbox';
            }
            
            // Check if this is an additional inbox folder
            $additionalInboxes = $this->account->getAdditionalInboxes();
            foreach ($additionalInboxes as $additionalInbox) {
                if ($folderName === $additionalInbox->folder_name || $fullName === $additionalInbox->folder_name) {
                    \Log::debug('Folder identified as additional inbox via database mapping', [
                        'folder' => $folderName,
                        'mapped_additional_inbox' => $additionalInbox->folder_name
                    ]);
                    return 'inbox';
                }
            }
        } catch (\Exception $e) {
            \Log::debug('Error checking database folder mappings, falling back to pattern matching', [
                'error' => $e->getMessage(),
                'folder' => $folderName
            ]);
        }
        
        // Fallback to pattern matching if no database mapping found
        $folderNameLower = strtolower($folderName);
        $fullNameLower = strtolower($fullName);
        
        // Check for spam/junk patterns - including LaPoste's QUARANTAINE
        $spamPatterns = [
            'spam', 'junk', 'bulk', 'courrier indésirable', 
            'indésirable', 'quarantaine', 'pourriel'
        ];
        
        foreach ($spamPatterns as $pattern) {
            if (strpos($folderNameLower, $pattern) !== false || strpos($fullNameLower, $pattern) !== false) {
                \Log::debug('Folder identified as spam via pattern matching', [
                    'folder' => $folderName,
                    'pattern' => $pattern
                ]);
                return 'spam';
            }
        }
        
        // Check for promotions patterns
        if (strpos($folderNameLower, 'promot') !== false || strpos($fullNameLower, 'promot') !== false) {
            \Log::debug('Folder identified as promotions via pattern matching', [
                'folder' => $folderName
            ]);
            return 'promotions';
        }
        
        // Check for social/forums patterns
        if (strpos($folderNameLower, 'social') !== false || strpos($folderNameLower, 'forum') !== false) {
            \Log::debug('Folder identified as promotions (social/forum) via pattern matching', [
                'folder' => $folderName
            ]);
            return 'promotions';
        }
        
        \Log::debug('Folder defaulting to inbox placement', [
            'folder' => $folderName
        ]);
        return 'inbox';
    }

    /**
     * Get email details
     */
    public function getEmail(string $messageId): ?array
    {
        try {
            $this->connect();
            
            // Try to use mapped inbox folder first, fallback to 'INBOX'
            $inboxFolder = $this->account->getInboxFolder() ?? 'INBOX';
            
            try {
                $folder = $this->client->getFolder($inboxFolder);
            } catch (\Exception $e) {
                \Log::debug('Could not access mapped inbox folder, trying INBOX', [
                    'mapped_folder' => $inboxFolder,
                    'error' => $e->getMessage()
                ]);
                $folder = $this->client->getFolder('INBOX');
            }
            $message = $folder->messages()->getMessageByUid($messageId);
            
            if ($message) {
                $email = $this->parseMessage($message);
                $this->disconnect();
                return $email;
            }
            
            $this->disconnect();
            return null;
        } catch (\Exception $e) {
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
            
            // Try to find the message in any folder
            $folders = $this->client->getFolders();
            
            foreach ($folders as $folder) {
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
            
            // Try mapped inbox folder first, then fallback to common inbox names
            $inboxNames = [];
            $mappedInboxFolder = $this->account->getInboxFolder();
            if ($mappedInboxFolder) {
                $inboxNames[] = $mappedInboxFolder;
            }
            $inboxNames = array_merge($inboxNames, ['INBOX', 'Inbox', 'inbox']);
            $folder = null;
            
            foreach ($inboxNames as $folderName) {
                try {
                    $folder = $this->client->getFolder($folderName);
                    if ($folder) {
                        \Log::debug('Successfully accessed inbox folder', [
                            'folder' => $folderName,
                            'account' => $this->account->email
                        ]);
                        break;
                    }
                } catch (\Exception $e) {
                    \Log::debug('Could not access inbox folder', [
                        'folder' => $folderName,
                        'account' => $this->account->email,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            if (!$folder) {
                \Log::warning('No mapped or standard inbox folder found, trying first available folder', [
                    'account' => $this->account->email,
                    'attempted_folders' => $inboxNames
                ]);
                
                // If no standard inbox found, get first folder
                try {
                    $folders = $this->client->getFolders();
                    if ($folders && count($folders) > 0) {
                        $folder = $folders->first();
                        \Log::debug('Using first available folder as fallback', [
                            'folder' => $folder->name,
                            'account' => $this->account->email
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Could not retrieve any folders', [
                        'account' => $this->account->email,
                        'error' => $e->getMessage()
                    ]);
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
    public function getEmailsFromFolder(string $folderName, int $limit = 10): array
    {
        try {
            $this->connect();
            
            $emails = [];
            $folder = null;
            
            // Try to find the folder by exact name or path
            $folder = $this->findFolderByName($folderName);
            
            if ($folder) {
                try {
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
                            
                            // Get headers for the email
                            $headers = '';
                            try {
                                $headers = $message->getHeader()->raw;
                            } catch (\Exception $e) {
                                \Log::debug('Could not get headers', ['error' => $e->getMessage()]);
                            }
                            
                            $emails[] = [
                                'id' => $message->getUid(),
                                'subject' => (string) $message->getSubject(),
                                'date' => $dateStr,
                                'from' => $message->getFrom()[0]->mail ?? '',
                                'folder' => $folderName,
                                'headers' => $headers,
                            ];
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Could not get messages from folder: ' . $folderName, [
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                \Log::warning('Folder not found: ' . $folderName);
            }
            
            $this->disconnect();
            return $emails;
        } catch (\Exception $e) {
            \Log::error('Error getting emails from folder: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Connect to IMAP
     */
    private function connect(): void
    {
        if ($this->client) {
            return;
        }

        $settings = $this->account->imap_settings;
        
        $config = [
            'host' => $settings['host'] ?? 'localhost',
            'port' => $settings['port'] ?? 993,
            'encryption' => $settings['encryption'] ?? 'ssl',
            'validate_cert' => $settings['validate_cert'] ?? true,
            'username' => $this->account->email,
            'password' => $this->getPassword(),
            'protocol' => 'imap',
            'authentication' => 'NORMAL'
        ];

        \Log::info('IMAP connection attempt', [
            'email' => $this->account->email,
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'username' => $config['username'],
            'imap_settings' => $settings
        ]);

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
        // Handle date formatting safely
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
        
        return [
            'uid' => $message->getUid(),
            'subject' => $message->getSubject(),
            'from' => $message->getFrom()[0]->mail ?? '',
            'date' => $dateStr,
            'placement' => 'inbox',
            'headers' => $message->getHeader()->raw,
            'body' => $message->getTextBody(),
            'html_body' => $message->getHTMLBody(),
        ];
    }
    
    /**
     * Get all folders recursively, including subfolders
     */
    private function getAllFoldersRecursively(): array
    {
        $allFolders = [];
        
        try {
            // Get root folders
            $folders = $this->client->getFolders();
            
            foreach ($folders as $folder) {
                // Add the folder name
                $allFolders[] = $folder->name;
                
                // If the folder has children, get them recursively
                if ($folder->hasChildren()) {
                    $this->addChildFolders($folder, $allFolders);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting folders recursively', [
                'error' => $e->getMessage()
            ]);
        }
        
        return array_unique($allFolders);
    }
    
    /**
     * Recursively add child folders to the list
     */
    private function addChildFolders($parentFolder, &$folderList): void
    {
        try {
            $children = $parentFolder->getChildren();
            
            foreach ($children as $child) {
                // Add the child folder
                $folderList[] = $child->name;
                
                // Also add the full path for compatibility
                if ($child->full_name && $child->full_name !== $child->name) {
                    $folderList[] = $child->full_name;
                }
                
                // If this child also has children, recurse
                if ($child->hasChildren()) {
                    $this->addChildFolders($child, $folderList);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting child folders', [
                'parent' => $parentFolder->name,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Find a folder by name or full path
     */
    private function findFolderByName(string $folderName)
    {
        try {
            // First try direct access
            try {
                $folder = $this->client->getFolder($folderName);
                if ($folder) {
                    return $folder;
                }
            } catch (\Exception $e) {
                // Continue searching
            }
            
            // Search recursively in all folders
            return $this->searchFolderRecursively($this->client->getFolders(), $folderName);
        } catch (\Exception $e) {
            \Log::warning('Error finding folder', [
                'folder' => $folderName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Search for a folder recursively
     */
    private function searchFolderRecursively($folders, string $targetName)
    {
        foreach ($folders as $folder) {
            // Check if this is the folder we're looking for
            if ($folder->name === $targetName || 
                $folder->full_name === $targetName ||
                strtolower($folder->name) === strtolower($targetName) ||
                strtolower($folder->full_name) === strtolower($targetName)) {
                return $folder;
            }
            
            // If folder has children, search them
            if ($folder->hasChildren()) {
                try {
                    $children = $folder->getChildren();
                    $found = $this->searchFolderRecursively($children, $targetName);
                    if ($found) {
                        return $found;
                    }
                } catch (\Exception $e) {
                    // Continue searching
                }
            }
        }
        
        return null;
    }
}