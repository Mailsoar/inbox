# Orchestrateur de Traitement des Emails

## Vue d'ensemble

L'orchestrateur de traitement des emails est un système automatisé qui :
- Traite les comptes email actifs en attente de tests
- Gère le traitement parallèle avec workers configurables
- Implémente un système de retry avec désactivation automatique après 3 échecs
- Collecte des métriques détaillées sur chaque traitement
- Nettoie automatiquement les anciennes données

## Architecture

### Composants principaux

1. **ProcessEmailsCommand** (`app/Console/Commands/ProcessEmailsCommand.php`)
   - Commande principale qui orchestre le traitement
   - Options de configuration : parallel, timeout, account, dry-run
   - Génère un UUID unique pour chaque run

2. **EmailProcessingOrchestrator** (`app/Services/EmailProcessingOrchestrator.php`)
   - Service principal qui gère le traitement des comptes
   - Détection automatique du placement (inbox/spam/promotions)
   - Parsing des headers d'authentification (SPF/DKIM/DMARC)
   - Détection des filtres anti-spam

3. **Tables de base de données**
   - `email_processing_metrics` : Métriques de chaque traitement
   - `email_account_failures` : Suivi des échecs et retry

### Flux de traitement

```
1. Commande lancée (chaque minute via cron)
   ↓
2. Récupération des comptes actifs avec tests en attente
   ↓
3. Exclusion des comptes en période de retry
   ↓
4. Traitement par chunks parallèles (4 workers par défaut)
   ↓
5. Pour chaque compte :
   - Connexion au service email (Gmail/Outlook/IMAP)
   - Recherche des emails avec l'ID unique du test
   - Détermination du placement
   - Analyse des headers d'authentification
   - Détection des filtres anti-spam
   - Enregistrement dans test_results
   ↓
6. Gestion des échecs :
   - Incrémentation du compteur d'échecs
   - Retry après 10 minutes
   - Désactivation après 3 échecs
   ↓
7. Métriques enregistrées pour monitoring
```

## Configuration

### Variables d'environnement

Aucune configuration spécifique requise. L'orchestrateur utilise les configurations existantes.

### Options de la commande

```bash
php artisan emails:process [options]
```

Options disponibles :
- `--parallel=N` : Nombre de workers parallèles (défaut: 4)
- `--timeout=N` : Timeout en secondes par compte (défaut: 300)
- `--account=ID` : Traiter des comptes spécifiques
- `--dry-run` : Mode simulation sans traitement réel

## Déploiement

### 1. Exécuter les migrations

```bash
php artisan migrate
```

Cela créera les tables :
- `email_processing_metrics`
- `email_account_failures`

### 2. Configurer le cron

Ajouter au crontab :

```bash
# Option 1 : Via le scheduler Laravel (recommandé)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Option 2 : Directement la commande
* * * * * cd /path/to/project && php artisan emails:process >> /dev/null 2>&1
```

### 3. Script de déploiement automatique

```bash
./scripts/deploy_orchestrator.sh
```

Ce script :
- Exécute les migrations
- Nettoie et reconstruit le cache
- Vérifie les commandes
- Crée les répertoires de logs
- Affiche les instructions de configuration

## Monitoring

### Logs

Les logs sont stockés dans :
- `storage/logs/email-processing.log` : Logs de traitement
- `storage/logs/metrics-cleanup.log` : Logs de nettoyage
- `storage/logs/laravel.log` : Logs généraux

Rotation automatique tous les 7 jours.

### Métriques en base de données

Requêtes SQL utiles :

```sql
-- Statistiques globales
SELECT 
    DATE(started_at) as date,
    COUNT(*) as runs,
    AVG(duration_seconds) as avg_duration,
    SUM(emails_found) as total_emails,
    SUM(errors_count) as total_errors
FROM email_processing_metrics
WHERE started_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(started_at);

-- Comptes en échec
SELECT 
    ea.email,
    ea.provider,
    eaf.failure_count,
    eaf.last_error,
    eaf.last_failure_at
FROM email_account_failures eaf
JOIN email_accounts ea ON ea.id = eaf.email_account_id
WHERE eaf.failure_count > 0;

-- Performance par provider
SELECT 
    ea.provider,
    COUNT(DISTINCT epm.email_account_id) as accounts,
    AVG(epm.duration_seconds) as avg_duration,
    SUM(epm.emails_found) as total_emails
FROM email_processing_metrics epm
JOIN email_accounts ea ON ea.id = epm.email_account_id
WHERE epm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ea.provider;
```

### Commandes de diagnostic

```bash
# Tester un compte spécifique
php artisan emails:process --account=123 --dry-run

# Voir les logs en temps réel
tail -f storage/logs/email-processing.log

# Nettoyer manuellement les métriques
php artisan metrics:clean --days=7

# Vérifier le statut du scheduler
php artisan schedule:list
```

## Gestion des erreurs

### Système de retry

- Après un échec : retry après 10 minutes
- Après 3 échecs : compte désactivé automatiquement
- Réactivation manuelle requise depuis l'admin

### Types d'erreurs gérées

1. **Erreurs de connexion** : OAuth expiré, mauvais identifiants
2. **Timeouts** : Connexion trop lente
3. **Erreurs IMAP** : Dossier introuvable, quota dépassé
4. **Erreurs de parsing** : Email malformé

## Performance

### Optimisations appliquées

1. **Traitement parallèle** : 4 workers par défaut
2. **Requêtes optimisées** : Utilisation de chunks et eager loading
3. **Cache** : Mise en cache des configurations providers
4. **Timeout** : Protection contre les connexions bloquantes

### Recommandations

- Ajuster `--parallel` selon les ressources serveur
- Monitorer la charge CPU/RAM pendant les pics
- Augmenter le timeout pour les comptes avec beaucoup d'emails
- Utiliser `--dry-run` pour tester les changements

## Sécurité

- Les tokens OAuth sont chiffrés en base de données
- Les logs ne contiennent pas d'informations sensibles
- Rotation automatique des logs tous les 7 jours
- Nettoyage automatique des métriques après 30 jours

## Troubleshooting

### L'orchestrateur ne traite pas les emails

1. Vérifier le cron : `crontab -l`
2. Vérifier les logs : `tail -100 storage/logs/email-processing.log`
3. Tester manuellement : `php artisan emails:process`

### Compte désactivé automatiquement

1. Vérifier les erreurs : 
```sql
SELECT * FROM email_account_failures WHERE email_account_id = ?;
```
2. Corriger le problème (token, mot de passe, etc.)
3. Réactiver depuis l'admin
4. Supprimer l'enregistrement de failure

### Performance dégradée

1. Réduire le nombre de workers : `--parallel=2`
2. Vérifier la charge serveur : `top` ou `htop`
3. Analyser les métriques pour identifier les comptes lents
4. Augmenter le timeout si nécessaire

## Évolutions futures

- [ ] Support IDLE/PUSH pour traitement temps réel
- [ ] Dashboard de monitoring dédié
- [ ] Alertes automatiques en cas d'échecs répétés
- [ ] API REST pour contrôle externe
- [ ] Support de webhooks pour notifications