<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\PlacementTest;
use App\Services\TestResultProcessor;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class ImapIdleService
{
    protected EmailAccount $emailAccount;
    protected Client $client;
    protected TestResultProcessor $resultProcessor;
    protected bool $running = true;
    protected int $reconnectAttempts = 0;
    protected int $maxReconnectAttempts = 5;
    protected array $watchedFolders = [];

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->resultProcessor = new TestResultProcessor();
    }

    /**
     * Start the IDLE listener for this email account
     */
    public function start(): void
    {
        Log::info("Starting IMAP IDLE for account: {$this->emailAccount->email}");
        
        while ($this->running && $this->reconnectAttempts < $this->maxReconnectAttempts) {
            try {
                $this->connect();
                $this->setupWatchedFolders();
                $this->startIdleLoop();
            } catch (\Exception $e) {
                Log::error("IMAP IDLE error for {$this->emailAccount->email}: " . $e->getMessage());
                $this->reconnectAttempts++;
                
                if ($this->reconnectAttempts < $this->maxReconnectAttempts) {
                    $delay = min(60 * $this->reconnectAttempts, 300); // Max 5 minutes
                    Log::info("Reconnecting in {$delay} seconds...");
                    sleep($delay);
                } else {
                    Log::error("Max reconnection attempts reached for {$this->emailAccount->email}");
                    $this->emailAccount->update([
                        'connection_status' => 'error',
                        'connection_error' => 'Max IDLE reconnection attempts reached'
                    ]);
                }
            }
        }
    }

    /**
     * Connect to IMAP server
     */
    protected function connect(): void
    {
        $config = $this->getImapConfig();
        
        $cm = new ClientManager();
        $this->client = $cm->make($config);
        
        try {
            $this->client->connect();
            Log::info("Connected to IMAP server for {$this->emailAccount->email}");
            $this->reconnectAttempts = 0; // Reset on successful connection
            
            $this->emailAccount->update([
                'connection_status' => 'success',
                'last_connection_test' => now()
            ]);
        } catch (ConnectionFailedException $e) {
            throw new \Exception("Failed to connect: " . $e->getMessage());
        }
    }

    /**
     * Get IMAP configuration based on provider
     */
    protected function getImapConfig(): array
    {
        $config = [
            'host' => '',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $this->emailAccount->email,
            'password' => '',
            'protocol' => 'imap',
            'authentication' => null,
        ];

        switch ($this->emailAccount->provider) {
            case 'gmail':
                $config['host'] = 'imap.gmail.com';
                if ($this->emailAccount->oauth_token) {
                    $config['authentication'] = 'oauth';
                    $config['password'] = decrypt($this->emailAccount->oauth_token);
                } else {
                    throw new \Exception("Gmail requires OAuth authentication");
                }
                break;

            case 'outlook':
            case 'microsoft':
                $config['host'] = 'outlook.office365.com';
                if ($this->emailAccount->oauth_token) {
                    $config['authentication'] = 'oauth';
                    $config['password'] = decrypt($this->emailAccount->oauth_token);
                } else {
                    throw new \Exception("Microsoft requires OAuth authentication");
                }
                break;

            case 'yahoo':
                $config['host'] = 'imap.mail.yahoo.com';
                $config['password'] = decrypt($this->emailAccount->password);
                break;

            case 'imap':
            case 'generic_imap': // Backward compatibility
                $settings = json_decode($this->emailAccount->imap_settings, true);
                $config['host'] = $settings['host'];
                $config['port'] = $settings['port'];
                $config['encryption'] = $settings['encryption'];
                $config['password'] = decrypt($this->emailAccount->password);
                break;

            default:
                throw new \Exception("Unsupported provider: {$this->emailAccount->provider}");
        }

        return $config;
    }

    /**
     * Setup folders to watch based on account configuration
     */
    protected function setupWatchedFolders(): void
    {
        try {
            $folderMapping = $this->emailAccount->folder_mapping ?? [];
            
            // Default folders to watch
            $defaultFolders = ['INBOX'];
            
            if (isset($folderMapping['spam'])) {
                $defaultFolders[] = $folderMapping['spam'];
            }
            if (isset($folderMapping['promotions'])) {
                $defaultFolders[] = $folderMapping['promotions'];
            }
            
            // Get actual folders
            $folders = $this->client->getFolders();
            
            if (empty($folders)) {
                Log::error("No folders found for {$this->emailAccount->email}");
                throw new \Exception("No folders available");
            }
            
            Log::info("Available folders for {$this->emailAccount->email}: " . implode(', ', array_map(function($f) { return $f->name; }, $folders->all())));
            
            foreach ($folders as $folder) {
                $folderName = $folder->name;
                $folderNameLower = strtolower($folderName);
                
                // Check if this folder should be watched
                $shouldWatch = false;
                
                // Check exact matches
                if (in_array($folderName, $defaultFolders)) {
                    $shouldWatch = true;
                }
                
                // Check common spam folder names
                if (in_array($folderNameLower, ['spam', 'junk', 'bulk', 'junk e-mail', 'bulk mail'])) {
                    $shouldWatch = true;
                }
                
                // Check common promotions folder names
                if (in_array($folderNameLower, ['promotions', 'promo', 'marketing'])) {
                    $shouldWatch = true;
                }
                
                // Check for INBOX variations
                if (stripos($folderName, 'inbox') !== false) {
                    $shouldWatch = true;
                }
                
                if ($shouldWatch) {
                    $this->watchedFolders[] = $folder;
                    Log::info("Watching folder: {$folderName} for {$this->emailAccount->email}");
                }
            }
            
            if (empty($this->watchedFolders)) {
                Log::warning("No folders matched watch criteria for {$this->emailAccount->email}, watching all folders");
                // Watch at least the first folder
                $this->watchedFolders[] = $folders->first();
            }
            
        } catch (\Exception $e) {
            Log::error("Error setting up folders for {$this->emailAccount->email}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start the IDLE loop
     */
    protected function startIdleLoop(): void
    {
        // First, verify we have folders to watch
        if (empty($this->watchedFolders)) {
            Log::warning("No folders to watch for {$this->emailAccount->email}");
            throw new \Exception("No folders available to watch");
        }

        // Check existing messages first
        foreach ($this->watchedFolders as $folder) {
            $this->processExistingMessages($folder);
        }

        // Find the primary folder for IDLE (prefer INBOX)
        $primaryFolder = null;
        foreach ($this->watchedFolders as $folder) {
            if (strtoupper($folder->name) === 'INBOX' || $folder->name === 'INBOX') {
                $primaryFolder = $folder;
                break;
            }
        }

        // If no INBOX, use the first folder
        if (!$primaryFolder && !empty($this->watchedFolders)) {
            $primaryFolder = $this->watchedFolders[0];
        }

        if (!$primaryFolder) {
            throw new \Exception("No primary folder found for IDLE");
        }
        
        Log::info("Starting IDLE loop on {$primaryFolder->name} for {$this->emailAccount->email}");
        
        // For now, assume IDLE is supported and handle errors gracefully
        $idleSupported = true;
        
        // Check if IDLE method exists
        if (method_exists($primaryFolder, 'idle')) {
            while ($this->running) {
                try {
                    // Enter IDLE mode (timeout after 29 minutes as per RFC)
                    $primaryFolder->idle(function($message) {
                        Log::info("New message detected via IDLE for {$this->emailAccount->email}");
                        $this->processMessage($message);
                    }, 1740); // 29 minutes
                    
                    // IDLE timed out, reconnect
                    // Log de timeout commenté pour éviter la pollution des logs
                    // Log::debug("IDLE timeout, reconnecting for {$this->emailAccount->email}");
                    
                    // Check other folders periodically
                    foreach ($this->watchedFolders as $folder) {
                        if ($folder->name !== $primaryFolder->name) {
                            $this->processNewMessages($folder);
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::error("IDLE loop error: " . $e->getMessage());
                    throw $e;
                }
            }
        } else {
            Log::warning("IDLE method not available, using polling mode for {$this->emailAccount->email}");
            $this->fallbackPolling();
        }
    }

    /**
     * Fallback to polling mode if IDLE is not supported
     */
    protected function fallbackPolling(): void
    {
        Log::info("Using polling mode for {$this->emailAccount->email}");
        
        while ($this->running) {
            foreach ($this->watchedFolders as $folder) {
                $this->processNewMessages($folder);
            }
            
            // Wait 60 seconds between polls
            sleep(60);
        }
    }

    /**
     * Process existing messages in a folder
     */
    protected function processExistingMessages($folder): void
    {
        try {
            // Look for recent unprocessed messages (last 24 hours)
            $since = now()->subDay();
            $query = $folder->messages()->since($since);
            
            // Try to disable body/attachment fetching if methods exist
            if (method_exists($query, 'setFetchBody')) {
                $query->setFetchBody(false);
            }
            if (method_exists($query, 'setFetchAttachments')) {
                $query->setFetchAttachments(false);
            }
            
            $messages = $query->get();

            foreach ($messages as $message) {
                $this->processMessage($message);
            }
        } catch (\Exception $e) {
            Log::error("Error processing existing messages in {$folder->name}: " . $e->getMessage());
        }
    }

    /**
     * Process new messages in a folder (for non-IDLE folders)
     */
    protected function processNewMessages($folder): void
    {
        try {
            // Check for messages from last 5 minutes
            $since = now()->subMinutes(5);
            $query = $folder->messages()->since($since)->unseen();
            
            // Try to disable body/attachment fetching if methods exist
            if (method_exists($query, 'setFetchBody')) {
                $query->setFetchBody(false);
            }
            if (method_exists($query, 'setFetchAttachments')) {
                $query->setFetchAttachments(false);
            }
            
            $messages = $query->get();

            foreach ($messages as $message) {
                $this->processMessage($message);
            }
        } catch (\Exception $e) {
            Log::error("Error processing new messages in {$folder->name}: " . $e->getMessage());
        }
    }

    /**
     * Process a single message
     */
    protected function processMessage($message): void
    {
        try {
            $subject = $message->getSubject();
            $body = $message->getTextBody() ?: $message->getHTMLBody();
            
            // Extract test ID
            if (preg_match('/MS-(\d{6})/', $subject . ' ' . $body, $matches)) {
                $uniqueId = 'MS-' . $matches[1];
                
                // Find the test
                $test = PlacementTest::where('unique_id', $uniqueId)
                    ->whereIn('status', ['sent', 'processing'])
                    ->first();

                if ($test) {
                    Log::info("Processing email for test {$uniqueId} via IDLE");
                    
                    // Determine placement based on folder
                    $folder = $message->getFolder();
                    $placement = $this->determinePlacement($folder->name);
                    
                    // Process the result
                    $this->resultProcessor->processEmailResult($test, $this->emailAccount, [
                        'message_id' => $message->getMessageId(),
                        'subject' => $subject,
                        'from' => $message->getFrom()[0]->mail ?? '',
                        'date' => $message->getDate(),
                        'placement' => $placement,
                        'folder' => $folder->name,
                        'headers' => $message->getHeader()->raw,
                        'body' => substr($body, 0, 1000), // Limit body size
                    ]);
                    
                    Log::info("Email processed successfully for test {$uniqueId}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error processing message: " . $e->getMessage());
        }
    }

    /**
     * Determine placement based on folder name
     */
    protected function determinePlacement(string $folderName): string
    {
        $folderLower = strtolower($folderName);
        $folderMapping = $this->emailAccount->folder_mapping ?? [];
        
        // Check mapped folders first
        if (isset($folderMapping['spam']) && $folderName === $folderMapping['spam']) {
            return 'spam';
        }
        if (isset($folderMapping['promotions']) && $folderName === $folderMapping['promotions']) {
            return 'promotions';
        }
        if (isset($folderMapping['inbox']) && $folderName === $folderMapping['inbox']) {
            return 'inbox';
        }
        
        // Fallback to name detection
        if (in_array($folderLower, ['spam', 'junk', 'bulk'])) {
            return 'spam';
        }
        if (in_array($folderLower, ['promotions', 'promo'])) {
            return 'promotions';
        }
        
        return 'inbox';
    }

    /**
     * Stop the IDLE listener
     */
    public function stop(): void
    {
        $this->running = false;
        if ($this->client) {
            $this->client->disconnect();
        }
        Log::info("Stopped IMAP IDLE for account: {$this->emailAccount->email}");
    }
}