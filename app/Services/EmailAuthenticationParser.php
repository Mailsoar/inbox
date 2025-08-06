<?php

namespace App\Services;

class EmailAuthenticationParser
{
    /**
     * Parse authentication results from email headers
     */
    public function parseAuthentication(string $headers): array
    {
        return [
            'spf' => $this->parseSPF($headers),
            'dkim' => $this->parseDKIM($headers),
            'dmarc' => $this->parseDMARC($headers),
            'bimi' => $this->parseBIMI($headers),
        ];
    }

    /**
     * Parse SPF result from headers
     */
    public function parseSPF(string $headers): array
    {
        $result = ['result' => 'none', 'details' => null];
        
        // First, handle multi-line Authentication-Results header
        if (preg_match('/Authentication-Results:([^\n]+(?:\n\s+[^\n]+)*)/i', $headers, $authMatch)) {
            $authHeader = str_replace(["\r\n", "\n", "\t"], ' ', $authMatch[1]);
            
            // Look for SPF in the collapsed header
            if (preg_match('/spf=(\w+)/i', $authHeader, $matches)) {
                $result['result'] = strtolower($matches[1]);
                
                // Extract details if available
                if (preg_match('/spf=\w+\s+([^;]+)/i', $authHeader, $detailMatch)) {
                    $result['details'] = trim($detailMatch[1]);
                }
                
                return $result;
            }
        }
        
        // Fallback patterns for other formats
        $patterns = [
            // ARC-Authentication-Results (LaPoste style)
            '/ARC-Authentication-Results:.*?spf=(\w+)/i',
            // Received-SPF header
            '/Received-SPF:\s*(\w+)/i',
            // SPF in authentication line with details
            '/spf=(\w+)\s*\([^)]*\)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $headers, $matches)) {
                $result['result'] = strtolower($matches[1]);
                break;
            }
        }
        
        // Extract SPF details if available
        if (preg_match('/spf=\w+\s*\(([^)]+)\)/i', $headers, $matches)) {
            $result['details'] = $matches[1];
        }
        
        return $result;
    }

    /**
     * Parse DKIM result from headers
     */
    public function parseDKIM(string $headers): array
    {
        $result = ['result' => 'none', 'selector' => null, 'domain' => null];
        
        // First, handle multi-line Authentication-Results header
        if (preg_match('/Authentication-Results:([^\n]+(?:\n\s+[^\n]+)*)/i', $headers, $authMatch)) {
            $authHeader = str_replace(["\r\n", "\n", "\t"], ' ', $authMatch[1]);
            
            // Look for DKIM results in the collapsed header
            if (preg_match_all('/dkim=(\w+)(?:\s+[^;]*header\.d=([^\s;]+))?/i', $authHeader, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $status = strtolower($match[1]);
                    $domain = isset($match[2]) ? $match[2] : null;
                    
                    // If we find a 'pass', use it
                    if ($status === 'pass') {
                        $result['result'] = 'pass';
                        $result['domain'] = $domain;
                        return $result;
                    }
                    
                    // Store first non-none result
                    if ($result['result'] === 'none') {
                        $result['result'] = $status;
                        $result['domain'] = $domain;
                    }
                }
            }
        }
        
        // Fallback: Look for DKIM results in various formats
        $patterns = [
            // ARC-Authentication-Results
            '/ARC-Authentication-Results:.*?dkim=(\w+)(?:\s+.*?header\.i=@([^\s;]+))?/i',
            // Multiple DKIM signatures
            '/dkim=(\w+)\s+header\.i=@([^\s;]+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $headers, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $status = strtolower($match[1]);
                    $domain = isset($match[2]) ? $match[2] : null;
                    
                    // If we find a 'pass', use it
                    if ($status === 'pass') {
                        $result['result'] = 'pass';
                        $result['domain'] = $domain;
                        return $result;
                    }
                }
            }
        }
        
        // Check for DKIM signature presence
        if ($result['result'] === 'none' && stripos($headers, 'DKIM-Signature:') !== false) {
            // DKIM signature present but verified - assume pass
            $result['result'] = 'pass';
        }
        
        // Map non-standard values to valid enum values
        if (!in_array($result['result'], ['pass', 'fail', 'none'])) {
            // neutral, temperror, permerror -> none
            $result['result'] = 'none';
        }
        
        return $result;
    }

    /**
     * Parse DMARC result from headers
     */
    public function parseDMARC(string $headers): array
    {
        $result = ['result' => 'none', 'policy' => null, 'disposition' => null];
        
        // First, handle multi-line Authentication-Results header
        if (preg_match('/Authentication-Results:([^\n]+(?:\n\s+[^\n]+)*)/i', $headers, $authMatch)) {
            $authHeader = str_replace(["\r\n", "\n", "\t"], ' ', $authMatch[1]);
            
            // Look for DMARC in the collapsed header
            if (preg_match('/dmarc=(\w+)(?:\s+([^;]+))?/i', $authHeader, $matches)) {
                $result['result'] = strtolower($matches[1]);
                
                // Extract policy and details if available
                if (isset($matches[2])) {
                    $details = $matches[2];
                    if (preg_match('/p=(\w+)/i', $details, $policyMatch)) {
                        $result['policy'] = strtoupper($policyMatch[1]);
                    }
                    if (preg_match('/dis=(\w+)/i', $details, $dispMatch)) {
                        $result['disposition'] = strtolower($dispMatch[1]);
                    }
                }
                
                return $result;
            }
        }
        
        // Fallback: DMARC patterns
        $patterns = [
            // ARC-Authentication-Results
            '/ARC-Authentication-Results:.*?dmarc=(\w+)(?:\s*\(p=([^)]+)\))?/i',
            // Format with action
            '/dmarc=(\w+)\s+action=(\w+)/i',
            // Simple format
            '/dmarc=(\w+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $headers, $matches)) {
                $result['result'] = strtolower($matches[1]);
                if (isset($matches[2])) {
                    // Could be policy (REJECT, QUARANTINE, NONE) or action
                    $result['policy'] = $matches[2];
                }
                break;
            }
        }
        
        // Extract DMARC policy details
        if (preg_match('/dmarc=\w+\s*\(([^)]+)\)/i', $headers, $matches)) {
            $details = $matches[1];
            if (preg_match('/p=(\w+)/i', $details, $policyMatch)) {
                $result['policy'] = strtoupper($policyMatch[1]);
            }
            if (preg_match('/dis=(\w+)/i', $details, $dispMatch)) {
                $result['disposition'] = strtolower($dispMatch[1]);
            }
        }
        
        return $result;
    }

    /**
     * Parse BIMI (Brand Indicators for Message Identification)
     */
    public function parseBIMI(string $headers): bool
    {
        // BIMI can appear in different ways:
        // 1. As explicit BIMI headers
        if (preg_match('/BIMI-Selector:|BIMI-Location:/i', $headers)) {
            return true;
        }
        
        // 2. In Authentication-Results
        if (preg_match('/bimi=pass/i', $headers)) {
            return true;
        }
        
        // 3. LaPoste style
        if (preg_match('/bimi=(\w+)/i', $headers, $matches)) {
            return strtolower($matches[1]) === 'pass';
        }
        
        return false;
    }

    /**
     * Extract sending IP from headers
     */
    public function extractSendingIP(string $headers): ?string
    {
        // Priority order for IP extraction
        $patterns = [
            // From Authentication-Results or SPF
            '/(?:client-ip|sender\s+IP\s+is|smtp\.remote-ip)=([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/i',
            // From Received headers with square brackets
            '/Received:.*?\[([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]/i',
            // From Received headers with parentheses (excluding internal IPs)
            '/Received:.*?from\s+[^\s]+\s+\(([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\)/i',
            // X-Originating-IP
            '/X-Originating-I[Pp]:\s*\[?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]?/i',
            // X-Sender-IP
            '/X-Sender-IP:\s*([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/i',
        ];
        
        $foundIPs = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $headers, $matches)) {
                foreach ($matches[1] as $ip) {
                    // Skip internal/private IPs
                    if (!$this->isPrivateIP($ip)) {
                        $foundIPs[] = $ip;
                    }
                }
            }
        }
        
        // Return the first public IP found
        return !empty($foundIPs) ? $foundIPs[0] : null;
    }

    /**
     * Check if IP is private/internal
     */
    private function isPrivateIP(string $ip): bool
    {
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '::1/128', // IPv6 localhost
            'fc00::/7', // IPv6 private
        ];
        
        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            return ($ip & $mask) == $subnet;
        }
        
        // IPv6 (simplified check)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // For now, just check exact match for IPv6
            return $ip === $subnet;
        }
        
        return false;
    }

    /**
     * Extract hostname from headers
     */
    public function extractHostname(string $headers): ?string
    {
        // Skip internal email provider servers and find the actual sending server
        $internalProviders = [
            // Gmail
            'mx.google.com', 'mail.google.com', 'googlemail.com',
            // LaPoste
            'laposte.net', 'mlpnf', 'mlpnb', 'sys.meshcore.net',
            // Microsoft/Outlook
            'outlook.com', 'outlook.office365.com', 'protection.outlook.com', 'prod.outlook.com',
            // Yahoo
            'yahoo.com', 'omega.yahoo.com', 'atlas-production',
            // Other common internal servers
            'localhost', 'localhost.localdomain'
        ];
        
        // Look for Received headers in reverse order (from bottom to top)
        // to find the original sending server
        $receivedHeaders = [];
        if (preg_match_all('/Received:\s+from\s+([a-zA-Z0-9\-\.]+)(?:\s+\(([^)]+)\))?\s+.*?(?:\r?\n(?!\s)|$)/si', $headers, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $hostname = $match[1];
                $details = isset($match[2]) ? $match[2] : '';
                
                // Skip if it's an internal provider server
                $isInternal = false;
                foreach ($internalProviders as $provider) {
                    if (stripos($hostname, $provider) !== false) {
                        $isInternal = true;
                        break;
                    }
                }
                
                if (!$isInternal && 
                    !filter_var($hostname, FILTER_VALIDATE_IP) &&
                    !preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $hostname)) {
                    $receivedHeaders[] = $hostname;
                }
            }
        }
        
        // Return the last (original) external hostname found
        if (!empty($receivedHeaders)) {
            return end($receivedHeaders);
        }
        
        // Fallback: Look for HELO/EHLO hostname
        $patterns = [
            '/(?:helo|ehlo)=([a-zA-Z0-9\-\.]+)/i',
            '/EHLO\s+([a-zA-Z0-9\-\.]+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $headers, $matches)) {
                $hostname = $matches[1];
                $isInternal = false;
                foreach ($internalProviders as $provider) {
                    if (stripos($hostname, $provider) !== false) {
                        $isInternal = true;
                        break;
                    }
                }
                
                if (!$isInternal && !filter_var($hostname, FILTER_VALIDATE_IP)) {
                    return $hostname;
                }
            }
        }
        
        return null;
    }

    /**
     * Extract Message-ID from headers
     */
    public function extractMessageId(string $headers): ?string
    {
        // Look for Message-ID header (case insensitive, can be multi-line)
        // Match patterns like: Message-Id: <20250804060554.e460950e552df346@mg.149.photos>
        if (preg_match('/^Message-I[Dd]:\s*<?([^>\s\r\n]+)>?/mi', $headers, $matches)) {
            $messageId = trim($matches[1], '<> ');
            // Validate it looks like a message ID (contains @ or looks like an ID)
            if (strpos($messageId, '@') !== false || preg_match('/^[a-zA-Z0-9._-]+$/', $messageId)) {
                return $messageId;
            }
        }
        
        return null;
    }

    /**
     * Extract From email and name from headers
     */
    public function extractFromInfo(string $headers): array
    {
        $result = ['email' => null, 'name' => null];
        
        // Look for From header (can be multi-line)
        if (preg_match('/^From:\s*(.+?)(?:\r?\n(?!\s)|$)/mi', $headers, $matches)) {
            // Handle multi-line headers by removing line breaks and extra spaces
            $from = preg_replace('/\s+/', ' ', trim($matches[1]));
            
            // Parse different From formats:
            // 1. "Name" <email@domain.com>
            // 2. Name <email@domain.com>
            // 3. email@domain.com (Name)
            // 4. email@domain.com
            
            // Format: "Name" <email> or Name <email>
            if (preg_match('/^"?([^"<>]*?)"?\s*<([^>]+)>/', $from, $parts)) {
                $name = trim($parts[1], ' "\' ');
                $email = trim($parts[2]);
                if (!empty($name)) {
                    $result['name'] = $name;
                }
                $result['email'] = $email;
            }
            // Format: email (Name)
            elseif (preg_match('/^([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s*\(([^)]+)\)/', $from, $parts)) {
                $result['email'] = $parts[1];
                $result['name'] = trim($parts[2]);
            }
            // Format: just email
            elseif (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $from, $parts)) {
                $result['email'] = $parts[1];
            }
        }
        
        // If no From header found, try Sender header as fallback
        if (empty($result['email']) && preg_match('/^Sender:\s*(.+?)$/mi', $headers, $matches)) {
            $sender = trim($matches[1]);
            if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $sender, $parts)) {
                $result['email'] = $parts[1];
            }
        }
        
        return $result;
    }
}