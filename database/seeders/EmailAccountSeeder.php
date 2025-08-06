<?php

namespace Database\Seeders;

use App\Models\EmailAccount;
use Illuminate\Database\Seeder;

class EmailAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Gmail accounts
            [
                'email' => 'test1@gmail.com',
                'name' => 'Test Gmail 1',
                'provider' => 'gmail',
                'account_type' => 'b2c',
                'auth_type' => 'oauth',
                'detected_antispam' => ['spamassassin'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.gmail'),
                'monitored_folders' => ['INBOX', '[Gmail]/Spam', '[Gmail]/Promotions'],
                'is_active' => true,
                'is_authenticated' => true,
            ],
            [
                'email' => 'test2@gmail.com',
                'name' => 'Test Gmail 2',
                'provider' => 'gmail',
                'account_type' => 'b2c',
                'auth_type' => 'oauth',
                'detected_antispam' => ['spamassassin', 'rspamd'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.gmail'),
                'monitored_folders' => ['INBOX', '[Gmail]/Spam'],
                'is_active' => true,
                'is_authenticated' => true,
            ],
            
            // Outlook accounts
            [
                'email' => 'test1@outlook.com',
                'name' => 'Test Outlook 1',
                'provider' => 'outlook',
                'account_type' => 'b2b',
                'auth_type' => 'oauth',
                'detected_antispam' => ['microsoft_defender'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.outlook'),
                'monitored_folders' => ['INBOX', 'Junk'],
                'is_active' => true,
                'is_authenticated' => true,
            ],
            [
                'email' => 'business@company.com',
                'name' => 'Business Account',
                'provider' => 'outlook',
                'account_type' => 'b2b',
                'auth_type' => 'oauth',
                'detected_antispam' => ['proofpoint', 'microsoft_defender'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.outlook'),
                'monitored_folders' => ['INBOX', 'Junk', 'Clutter'],
                'is_active' => true,
                'is_authenticated' => true,
            ],
            
            // Yahoo account
            [
                'email' => 'test@yahoo.com',
                'name' => 'Test Yahoo',
                'provider' => 'yahoo',
                'account_type' => 'b2c',
                'auth_type' => 'password',
                'detected_antispam' => ['spamassassin'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.yahoo'),
                'monitored_folders' => ['INBOX', 'Bulk Mail'],
                'is_active' => true,
                'is_authenticated' => true,
                'imap_host' => 'imap.mail.yahoo.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => 'test@yahoo.com',
                'imap_password' => encrypt('dummy_password'),
            ],
            
            // IMAP accounts with specific antispam
            [
                'email' => 'secure@enterprise.com',
                'name' => 'Enterprise Secure',
                'provider' => 'imap',
                'account_type' => 'b2b',
                'auth_type' => 'password',
                'detected_antispam' => ['barracuda', 'mimecast'],
                'folder_mapping' => config('mailsoar.default_folder_mappings.default'),
                'monitored_folders' => ['INBOX', 'Spam'],
                'is_active' => true,
                'is_authenticated' => true,
                'imap_host' => 'mail.enterprise.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => 'secure@enterprise.com',
                'imap_password' => encrypt('dummy_password'),
            ],
        ];

        foreach ($accounts as $account) {
            EmailAccount::create($account);
        }
    }
}