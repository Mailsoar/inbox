<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FilterRule;

class FilterRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Règle de normalisation des emails
        FilterRule::updateOrCreate(
            [
                'type' => 'email_pattern',
                'value' => 'normalization_settings'
            ],
            [
                'action' => 'allow',
                'is_active' => true,
                'description' => 'Configuration de la normalisation des emails (alias + et points Gmail)',
                'options' => [
                    'normalize_gmail_dots' => true,
                    'normalize_plus_aliases' => true,
                    'gmail_domains' => ['gmail.com', 'googlemail.com'],
                    'outlook_domains' => ['outlook.com', 'hotmail.com', 'live.com', 'msn.com']
                ]
            ]
        );

        // 2. Bloquer les domaines temporaires courants
        $tempDomains = [
            'guerrillamail.com' => 'Guerrilla Mail - Service email temporaire',
            '10minutemail.com' => '10 Minute Mail - Email temporaire',
            'tempmail.com' => 'Temp Mail - Service email temporaire',
            'throwawaymail.com' => 'Throwaway Mail - Email jetable',
            'mailinator.com' => 'Mailinator - Email public temporaire',
            'yopmail.com' => 'YOPmail - Email temporaire',
            'trashmail.com' => 'TrashMail - Service email jetable',
            'getnada.com' => 'Nada - Email temporaire',
            'temp-mail.org' => 'Temp Mail - Email temporaire',
            'maildrop.cc' => 'MailDrop - Email temporaire',
            'mintemail.com' => 'MintEmail - Email temporaire de 10 minutes',
            'sharklasers.com' => 'Guerrilla Mail domaine alternatif',
            'grr.la' => 'Guerrilla Mail domaine court',
            'mailnesia.com' => 'Mailnesia - Email temporaire anonyme',
            'tempr.email' => 'Tempr.email - Email temporaire',
            'emailondeck.com' => 'Email on Deck - Email temporaire',
            'mohmal.com' => 'Mohmal - Email temporaire arabe',
            'fakeinbox.com' => 'Fake Inbox - Email temporaire',
            'burnermail.io' => 'Burner Mail - Email temporaire',
            'inboxkitten.com' => 'Inbox Kitten - Email temporaire mignon'
        ];

        foreach ($tempDomains as $domain => $description) {
            FilterRule::updateOrCreate(
                [
                    'type' => 'domain',
                    'value' => $domain
                ],
                [
                    'action' => 'block',
                    'is_active' => true,
                    'description' => $description
                ]
            );
        }

        // 3. Bloquer les sous-domaines des services temporaires
        $wildcardDomains = [
            '*.guerrillamail.com' => 'Tous les sous-domaines de Guerrilla Mail',
            '*.mailinator.com' => 'Tous les sous-domaines de Mailinator',
            '*.yopmail.com' => 'Tous les sous-domaines de YOPmail',
            '*.temp-mail.org' => 'Tous les sous-domaines de Temp Mail'
        ];

        foreach ($wildcardDomains as $domain => $description) {
            FilterRule::updateOrCreate(
                [
                    'type' => 'domain',
                    'value' => $domain
                ],
                [
                    'action' => 'block',
                    'is_active' => true,
                    'description' => $description
                ]
            );
        }

        // 4. Bloquer certaines IP suspectes (exemples)
        $suspiciousIPs = [
            '192.168.*.*' => 'Réseau local privé - Non autorisé pour les tests publics',
            '10.*.*.*' => 'Réseau privé - Non autorisé pour les tests publics',
            '172.16.*.*' => 'Réseau privé - Non autorisé pour les tests publics',
            '127.0.0.1' => 'Localhost - Non autorisé'
        ];

        foreach ($suspiciousIPs as $ip => $description) {
            FilterRule::updateOrCreate(
                [
                    'type' => 'ip',
                    'value' => $ip
                ],
                [
                    'action' => 'block',
                    'is_active' => false, // Désactivé par défaut car pourrait bloquer des tests légitimes
                    'description' => $description
                ]
            );
        }

        // 5. Exemples de règles MX pour bloquer certains providers
        $mxRules = [
            'mx.fakemailgenerator.com' => 'Fake Mail Generator - Service email temporaire',
            'mail.guerrillamail.com' => 'Guerrilla Mail MX server'
        ];

        foreach ($mxRules as $mx => $description) {
            FilterRule::updateOrCreate(
                [
                    'type' => 'mx',
                    'value' => $mx
                ],
                [
                    'action' => 'block',
                    'is_active' => true,
                    'description' => $description
                ]
            );
        }

        // 6. Règles d'autorisation spéciales (exemples)
        // Ces règles permettent de créer des exceptions
        $allowedDomains = [
            'mailsoar.com' => 'Domaine MailSoar - Toujours autorisé',
            'test.mailsoar.com' => 'Domaine de test MailSoar - Toujours autorisé'
        ];

        foreach ($allowedDomains as $domain => $description) {
            FilterRule::updateOrCreate(
                [
                    'type' => 'domain',
                    'value' => $domain
                ],
                [
                    'action' => 'allow',
                    'is_active' => true,
                    'description' => $description
                ]
            );
        }

        $this->command->info('Filter rules seeded successfully!');
        $this->command->info('- Email normalization settings configured');
        $this->command->info('- ' . count($tempDomains) . ' temporary email domains blocked');
        $this->command->info('- ' . count($wildcardDomains) . ' wildcard domain rules added');
        $this->command->info('- ' . count($suspiciousIPs) . ' IP rules added (disabled by default)');
        $this->command->info('- ' . count($mxRules) . ' MX server rules added');
        $this->command->info('- ' . count($allowedDomains) . ' allowed domain exceptions added');
    }
}