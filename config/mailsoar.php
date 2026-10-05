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
    // Page de suivi : on affiche les résultats dès que cette part des boîtes
    // a répondu, ou ce délai après le lancement du test (même sans email reçu).
    'results_reveal_ratio' => env('RESULTS_REVEAL_RATIO', 0.8),
    'results_reveal_delay_seconds' => env('RESULTS_REVEAL_DELAY_SECONDS', 120),
    'max_email_size_kb' => env('MAX_EMAIL_SIZE_KB', 500),

    // Destinataires des alertes de connexion des boîtes de test (séparés par des virgules)
    'alert_emails' => env('ADMIN_ALERT_EMAILS', env('ADMIN_EMAIL', '')),

    /*
    |--------------------------------------------------------------------------
    | Alerte Slack sur fort taux de spam
    |--------------------------------------------------------------------------
    | À la fin d'un test, si la part des emails reçus classés en spam atteint
    | le seuil, un message part dans le canal de suivi commercial.
    */
    'spam_alert' => [
        'enabled' => env('SPAM_ALERT_ENABLED', true),
        // En pourcentage des emails reçus ; l'alerte part à partir de ce seuil.
        'threshold' => env('SPAM_ALERT_THRESHOLD', 25),
        // Un nouveau test du même domaine dans ce délai répond dans le fil existant.
        'thread_days' => env('SPAM_ALERT_THREAD_DAYS', 7),
        // Domaines jamais signalés (tests internes), séparés par des virgules.
        'excluded_domains' => env('SPAM_ALERT_EXCLUDED_DOMAINS', 'mailsoar.com'),
    ],

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

    /*
     * Agenda « Parler à un expert délivrabilité » ouvert en popup sous les
     * résultats. Surchargeable par MAILSOAR_EXPERT_URL dans le .env.
     */
    'expert_booking_url' => env(
        'MAILSOAR_EXPERT_URL',
        'https://calendly.com/pierre-mailsoar/talk-expert-test-deliverability'
    ),
];
