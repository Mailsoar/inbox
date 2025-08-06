<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AntispamSystem;

class AntispamSystemSeeder extends Seeder
{
    public function run()
    {
        $systems = [
            [
                'name' => 'microsoft',
                'display_name' => 'Microsoft/Outlook',
                'description' => 'Microsoft Defender for Office 365',
                'header_patterns' => ['X-Microsoft-Antispam', 'X-MS-Exchange-Organization-SCL'],
                'mx_patterns' => ['*.protection.outlook.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'gmail',
                'display_name' => 'Gmail (Google)',
                'description' => 'Google Postmaster & Spam Filters',
                'header_patterns' => ['X-Gm-Message-State', 'ARC-Authentication-Results'],
                'mx_patterns' => ['*.google.com', '*.googlemail.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'yahoo',
                'display_name' => 'Yahoo Mail',
                'description' => 'Yahoo SpamGuard',
                'header_patterns' => ['X-YMail'],
                'mx_patterns' => ['*.yahoodns.net', '*.yahoo.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'spamassassin',
                'display_name' => 'SpamAssassin',
                'description' => 'Apache SpamAssassin',
                'header_patterns' => ['X-Spam-Flag', 'X-Spam-Score', 'X-Spam-Status'],
                'mx_patterns' => [],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'barracuda',
                'display_name' => 'Barracuda',
                'description' => 'Barracuda Email Security Gateway',
                'header_patterns' => ['X-Barracuda-', 'X-ASG-'],
                'mx_patterns' => ['*.barracudanetworks.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'mimecast',
                'display_name' => 'Mimecast',
                'description' => 'Mimecast Email Security',
                'header_patterns' => ['X-Mimecast-'],
                'mx_patterns' => ['*.mimecast.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'proofpoint',
                'display_name' => 'Proofpoint',
                'description' => 'Proofpoint Email Protection',
                'header_patterns' => ['X-Proofpoint-', 'X-PPE'],
                'mx_patterns' => ['*.pphosted.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'sophos',
                'display_name' => 'Sophos',
                'description' => 'Sophos Email Security',
                'header_patterns' => ['X-Sophos-'],
                'mx_patterns' => ['*.sophos.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'trend_micro',
                'display_name' => 'Trend Micro',
                'description' => 'Trend Micro Email Security',
                'header_patterns' => ['X-TM-AS-', 'X-TMASE-'],
                'mx_patterns' => ['*.trendmicro.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'symantec',
                'display_name' => 'Symantec',
                'description' => 'Symantec Email Security',
                'header_patterns' => ['X-Brightmail-'],
                'mx_patterns' => ['*.messagelabs.com'],
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'name' => 'abusix',
                'display_name' => 'Abusix',
                'description' => 'Système anti-spam Abusix',
                'header_patterns' => ['X-Abusix'],
                'mx_patterns' => null,
                'is_custom' => false,
                'is_active' => true,
            ],
        ];

        foreach ($systems as $system) {
            AntispamSystem::updateOrCreate(
                ['name' => $system['name']],
                $system
            );
        }
    }
}