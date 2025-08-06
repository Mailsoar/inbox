<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\PlacementTest;
use App\Services\TestResultProcessor;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class ImapRealtimeService
{
    protected EmailAccount $emailAccount;
    protected Client $client;
    protected TestResultProcessor $resultProcessor;
    protected bool $running = true;
    protected int $reconnectAttempts = 0;
    protected int $maxReconnectAttempts = 5;
    protected array $watchedFolders = [];
    protected array $processedMessageIds = [];
    protected int $pollInterval = 10; // Start with 10 seconds

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->resultProcessor = new TestResultProcessor();
    }

    /**
     * Start the realtime monitoring
     */
    public function start(): void
    {
        Log::info("Starting realtime email monitoring for: {$this->emailAccount->email}");
        
        while ($this->running && $this->reconnectAttempts < $this->maxReconnectAttempts) {
            try {
                $this->connect();
                $this->setupWatchedFolders();
                $this->startMonitoring();
            } catch (\Exception $e) {
                Log::error("Realtime monitoring error for {$this->emailAccount->email}: " . $e->getMessage());
                $this->reconnectAttempts++;
                
                if ($this->reconnectAttempts < $this->maxReconnectAttempts) {
                    $delay = min(60 * $this->reconnectAttempts, 300); // Max 5 minutes
                    Log::info("Reconnecting in {$delay} seconds...");
                    sleep($delay);
                } else {
                    Log::error("Max reconnection attempts reached for {$this->emailAccount->email}");
                    $this->emailAccount->update([
                        'connection_status' => 'error',
                        'connection_error' => 'Max reconnection attempts reached'
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
     * Setup folders to watch
     */
    protected function setupWatchedFolders(): void
    {
        try {
            // Get all folders
            $folders = $this->client->getFolders();
            
            if (empty($folders)) {
                throw new \Exception("No folders available");
            }
            
            // List all available folders for debugging
            $folderNames = [];
            foreach ($folders as $folder) {
                $folderNames[] = $folder->name;
            }
            Log::info("Available folders for {$this->emailAccount->email}: " . implode(', ', $folderNames));
            
            // Add folders to watch based on common patterns
            foreach ($folders as $folder) {
                $folderName = $folder->name;
                $folderNameLower = strtolower($folderName);
                
                // Always watch these folders
                if (stripos($folderName, 'inbox') !== false ||
                    in_array($folderNameLower, ['spam', 'junk', 'bulk', 'junk e-mail', 'bulk mail']) ||
                    in_array($folderNameLower, ['promotions', 'promo', 'marketing'])) {
                    
                    $this->watchedFolders[] = $folder;
                    Log::info("Watching folder: {$folderName}");
                }
            }
            
            // If no folders matched, watch at least the first one
            if (empty($this->watchedFolders)) {
                $this->watchedFolders[] = $folders->first();
                Log::warning("No standard folders found, watching: " . $folders->first()->name);
            }
            
        } catch (\Exception $e) {
            Log::error("Error setting up folders: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start monitoring with smart polling
     */
    protected function startMonitoring(): void
    {
        Log::info("Starting smart polling for {$this->emailAccount->email}");
        
        $lastCheckTime = now()->subMinutes(5); // Check last 5 minutes initially
        $noNewMessagesCount = 0;
        
        while ($this->running) {
            try {
                $foundNewMessages = false;
                
                foreach ($this->watchedFolders as $folder) {
                    // Search for messages since last check
                    $messages = $folder->messages()
                        ->since($lastCheckTime->format('d-M-Y'))
                        ->get();
                    
                    foreach ($messages as $message) {
                        $messageId = $message->getMessageId();
                        
                        // Skip if already processed
                        if (in_array($messageId, $this->processedMessageIds)) {
                            continue;
                        }
                        
                        // Check if message date is after last check
                        $messageDate = $message->getDate();
                        if ($messageDate && $messageDate->timestamp > $lastCheckTime->timestamp) {
                            Log::info("New message found in {$folder->name}");
                            $this->processMessage($message, $folder);
                            $this->processedMessageIds[] = $messageId;
                            $foundNewMessages = true;
                            
                            // Keep only last 1000 message IDs to prevent memory issues
                            if (count($this->processedMessageIds) > 1000) {
                                $this->processedMessageIds = array_slice($this->processedMessageIds, -500);
                            }
                        }
                    }
                }
                
                // Adjust poll interval based on activity
                if ($foundNewMessages) {
                    $this->pollInterval = 10; // Fast polling when active
                    $noNewMessagesCount = 0;
                } else {
                    $noNewMessagesCount++;
                    // Gradually increase interval up to 60 seconds
                    if ($noNewMessagesCount > 5) {
                        $this->pollInterval = min($this->pollInterval + 10, 60);
                    }
                }
                
                $lastCheckTime = now();
                // Log de polling commenté pour éviter la pollution des logs
                // Log::debug("Polling {$this->emailAccount->email} - Next check in {$this->pollInterval} seconds");
                
                sleep($this->pollInterval);
                
            } catch (\Exception $e) {
                Log::error("Monitoring error: " . $e->getMessage());
                // Try to continue unless it's a connection error
                if (strpos($e->getMessage(), 'connection') !== false) {
                    throw $e;
                }
                sleep(30); // Wait before retry
            }
        }
    }

    /**
     * Process a message
     */
    protected function processMessage($message, $folder): void
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
                    Log::info("Processing email for test {$uniqueId}");
                    
                    // Determine placement
                    $placement = $this->determinePlacement($folder->name);
                    
                    // Get headers
                    $headers = '';
                    if ($message->getHeader()) {
                        $headers = $message->getHeader()->raw;
                    }
                    
                    // Process the result
                    $this->resultProcessor->processEmailResult($test, $this->emailAccount, [
                        'message_id' => $message->getMessageId(),
                        'subject' => $subject,
                        'from' => $message->getFrom()[0]->mail ?? '',
                        'date' => $message->getDate(),
                        'placement' => $placement,
                        'folder' => $folder->name,
                        'headers' => $headers,
                        'body' => substr($body, 0, 1000),
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
        
        if (in_array($folderLower, ['spam', 'junk', 'bulk', 'junk e-mail', 'bulk mail'])) {
            return 'spam';
        }
        if (in_array($folderLower, ['promotions', 'promo', 'marketing'])) {
            return 'promotions';
        }
        
        return 'inbox';
    }

    /**
     * Stop monitoring
     */
    public function stop(): void
    {
        $this->running = false;
        if ($this->client) {
            $this->client->disconnect();
        }
        Log::info("Stopped realtime monitoring for: {$this->emailAccount->email}");
    }
}