<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Créer la nouvelle table unifiée email_providers
        Schema::create('email_providers_new', function (Blueprint $table) {
            $table->id();
            
            // Identification
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            
            // Classification
            $table->enum('provider_type', ['b2c', 'b2b', 'custom', 'temporary', 'blacklisted', 'discontinued'])->default('b2c');
            $table->boolean('is_valid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('detection_priority')->default(100);
            
            // Configuration IMAP
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->default(993);
            $table->enum('imap_encryption', ['ssl', 'tls', 'none'])->default('ssl');
            $table->boolean('validate_cert')->default(true);
            
            // Configuration SMTP
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->default(587);
            $table->enum('smtp_encryption', ['ssl', 'tls', 'none'])->default('tls');
            
            // OAuth
            $table->boolean('supports_oauth')->default(false);
            $table->string('oauth_provider', 50)->nullable();
            
            // Détection
            $table->json('domains')->nullable(); // Liste des domaines
            $table->json('mx_patterns')->nullable(); // Patterns MX pour la détection
            
            // Configuration
            $table->boolean('requires_app_password')->default(false);
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            
            // Logo/Image
            $table->string('logo_url')->nullable();
            
            $table->timestamps();
            
            $table->index('name');
            $table->index('provider_type');
            $table->index('is_active');
        });
        
        // 2. Migrer les données depuis imap_providers (plus complète)
        $imapProviders = DB::table('imap_providers')->get();
        foreach ($imapProviders as $provider) {
            // Déterminer le provider_type basé sur le nom
            $providerType = 'b2c'; // Par défaut
            if (in_array($provider->name, ['gmail', 'outlook', 'yahoo', 'laposte', 'orange', 'sfr', 'free'])) {
                $providerType = 'b2c';
            } elseif ($provider->name === 'custom') {
                $providerType = 'custom';
            }
            
            // Chercher si existe dans email_providers pour récupérer le type
            $emailProvider = DB::table('email_providers')
                ->where('name', $provider->name)
                ->first();
            
            if ($emailProvider) {
                $providerType = $emailProvider->provider_type ?: $emailProvider->type ?: 'b2c';
            }
            
            DB::table('email_providers_new')->insert([
                'name' => $provider->name,
                'display_name' => $provider->display_name,
                'description' => $provider->description,
                'provider_type' => $providerType,
                'is_valid' => $emailProvider->is_valid ?? true,
                'is_active' => $provider->is_active,
                'detection_priority' => $emailProvider->detection_priority ?? 100,
                'imap_host' => $provider->imap_host,
                'imap_port' => $provider->imap_port,
                'imap_encryption' => $provider->imap_encryption,
                'validate_cert' => $provider->validate_cert,
                'smtp_host' => $provider->smtp_host,
                'smtp_port' => $provider->smtp_port,
                'smtp_encryption' => $provider->smtp_encryption,
                'supports_oauth' => $provider->supports_oauth,
                'oauth_provider' => $provider->oauth_provider,
                'domains' => $provider->domains ?: ($provider->common_domains ?: null),
                'mx_patterns' => $provider->mx_patterns ?: ($emailProvider->mx_patterns ?? null),
                'requires_app_password' => $provider->requires_app_password,
                'instructions' => $provider->instructions,
                'notes' => $emailProvider->notes ?? null,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ]);
        }
        
        // 3. Ajouter les providers qui sont seulement dans email_providers
        $onlyInEmailProviders = DB::table('email_providers')
            ->whereNotIn('name', function($query) {
                $query->select('name')->from('imap_providers');
            })
            ->get();
            
        foreach ($onlyInEmailProviders as $provider) {
            DB::table('email_providers_new')->insert([
                'name' => $provider->name,
                'display_name' => $provider->display_name,
                'provider_type' => $provider->provider_type ?: $provider->type ?: 'b2c',
                'is_valid' => $provider->is_valid,
                'is_active' => $provider->is_active,
                'detection_priority' => $provider->detection_priority ?? 100,
                'mx_patterns' => $provider->mx_patterns,
                'notes' => $provider->notes,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ]);
        }
        
        // 4. Renommer les tables
        Schema::rename('email_providers', 'email_providers_old');
        Schema::rename('email_providers_new', 'email_providers');
        
        // 5. Optionnel : garder imap_providers pour compatibilité temporaire
        // Schema::rename('imap_providers', 'imap_providers_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer l'ancienne table
        if (Schema::hasTable('email_providers_old')) {
            Schema::dropIfExists('email_providers');
            Schema::rename('email_providers_old', 'email_providers');
        }
        
        // if (Schema::hasTable('imap_providers_old')) {
        //     Schema::rename('imap_providers_old', 'imap_providers');
        // }
    }
};