<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Bot Slack du canal de suivi des leads (#internal-leads-inbox)
    'slack_feedback' => [
        'bot_token' => env('FEEDBACK_SLACK_BOT_TOKEN'),
        'channel_id' => env('FEEDBACK_SLACK_CHANNEL_ID'),
        // Canal technique (#development) : boîtes de test déconnectées
        'alerts_channel_id' => env('SLACK_ALERTS_CHANNEL_ID', 'C0A3W5GAECE'),
    ],

    // Application privée HubSpot : création des leads lors des alertes spam
    'hubspot' => [
        'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
        'portal_id' => env('HUBSPOT_PORTAL_ID', '24884708'),
        'ui_domain' => env('HUBSPOT_UI_DOMAIN', 'app-eu1.hubspot.com'),
        // Propriétaire des nouveaux leads selon la langue du test ; toute
        // langue autre que le français part chez Larry.
        'lead_owners' => [
            'en' => ['id' => env('HUBSPOT_LEAD_OWNER_EN', '34698354'), 'name' => 'Larry'],
            'fr' => ['id' => env('HUBSPOT_LEAD_OWNER_FR', '189883892'), 'name' => 'Pierre'],
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'allowed_domains' => env('GOOGLE_ALLOWED_DOMAINS', 'mailsoar.com'),
        'allowed_emails' => env('GOOGLE_ALLOWED_EMAILS', ''),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'gmail' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GMAIL_REDIRECT_URI', 'https://inbox.mailsoar.com/oauth/gmail/callback'),
    ],

];
