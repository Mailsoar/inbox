<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit_per_email' => env('RATE_LIMIT_PER_EMAIL', 50), // Increased for testing
    'rate_limit_per_ip' => env('RATE_LIMIT_PER_IP', 100), // Increased for testing

    /*
    |--------------------------------------------------------------------------
    | Test Configuration
    |--------------------------------------------------------------------------
    */
    'test_retention_days' => env('TEST_RETENTION_DAYS', 7),
    'email_retention_days' => env('EMAIL_RETENTION_DAYS', 30),
    'email_check_timeout_minutes' => env('EMAIL_CHECK_TIMEOUT_MINUTES', 30),
    'default_test_size' => env('DEFAULT_TEST_SIZE', 25),
    'max_email_size_kb' => env('MAX_EMAIL_SIZE_KB', 500),

    /*
    |--------------------------------------------------------------------------
    | Email Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gmail' => [
            'name' => 'Gmail',
            'auth_type' => 'oauth',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
        ],
        'outlook' => [
            'name' => 'Outlook/Microsoft',
            'auth_type' => 'oauth',
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
        ],
        'yahoo' => [
            'name' => 'Yahoo',
            'auth_type' => 'password',
            'imap_host' => 'imap.mail.yahoo.com',
            'imap_port' => 993,
        ],
        'imap' => [
            'name' => 'IMAP (Autre)',
            'auth_type' => 'password',
            'imap_host' => null, // User must provide
            'imap_port' => 993,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-spam Filters
    |--------------------------------------------------------------------------
    */
    'antispam_filters' => [
        'proofpoint' => 'Proofpoint',
        'vadesecure' => 'Vade Secure',
        'spamassassin' => 'SpamAssassin',
        'barracuda' => 'Barracuda',
        'mimecast' => 'Mimecast',
        'symantec' => 'Symantec',
        'trend_micro' => 'Trend Micro',
        'cisco' => 'Cisco IronPort',
        'sophos' => 'Sophos',
        'cloudmark' => 'Cloudmark',
        'rspamd' => 'Rspamd',
        'microsoft_defender' => 'Microsoft Defender',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklists
    |--------------------------------------------------------------------------
    */
    'blacklists' => [
        'spamhaus' => [
            'name' => 'Spamhaus ZEN',
            'dns' => 'zen.spamhaus.org',
        ],
        'barracuda' => [
            'name' => 'Barracuda',
            'dns' => 'b.barracudacentral.org',
        ],
        'spamcop' => [
            'name' => 'SpamCop',
            'dns' => 'bl.spamcop.net',
        ],
        'sorbs' => [
            'name' => 'SORBS',
            'dns' => 'dnsbl.sorbs.net',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder Mappings
    |--------------------------------------------------------------------------
    */
    'default_folder_mappings' => [
        'gmail' => [
            'INBOX' => 'inbox',
            '[Gmail]/Spam' => 'spam',
            '[Gmail]/Promotions' => 'promotions',
            '[Gmail]/Updates' => 'updates',
            '[Gmail]/Forums' => 'forums',
        ],
        'outlook' => [
            'INBOX' => 'inbox',
            'Junk' => 'spam',
            'Clutter' => 'promotions',
        ],
        'yahoo' => [
            'INBOX' => 'inbox',
            'Bulk Mail' => 'spam',
        ],
        'default' => [
            'INBOX' => 'inbox',
            'Spam' => 'spam',
            'Junk' => 'spam',
        ],
    ],
];