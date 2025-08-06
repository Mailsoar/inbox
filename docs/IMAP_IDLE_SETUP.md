# IMAP IDLE Setup Guide

## Overview

IMAP IDLE permet une détection en temps réel des emails entrants, éliminant les délais des APIs et du polling traditionnel.

## Architecture

- **1 processus PHP par compte email** : Chaque compte maintient une connexion IMAP persistante
- **Détection instantanée** : Les emails sont détectés dès leur arrivée (quelques secondes)
- **Support multi-dossiers** : Surveille INBOX, Spam, Promotions simultanément
- **Reconnexion automatique** : Gestion des déconnexions avec retry exponential

## Installation

### 1. Test manuel d'un compte

```bash
# Tester un compte spécifique
php artisan imap:idle-listener exemple@gmail.com
```

### 2. Gestion de tous les comptes

```bash
# Voir le statut
php artisan imap:idle-manager status

# Démarrer tous les listeners
php artisan imap:idle-manager start

# Arrêter tous les listeners
php artisan imap:idle-manager stop

# Redémarrer tous les listeners
php artisan imap:idle-manager restart
```

### 3. Configuration Supervisor (Production)

#### Option A : Un seul processus manager

```bash
# Copier la config supervisor
sudo cp config/supervisor/imap-idle.conf /etc/supervisor/conf.d/

# Recharger supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start imap-idle-manager
```

#### Option B : Un processus par compte (recommandé)

```bash
# Générer les configs pour chaque compte
php scripts/generate-supervisor-configs.php

# Copier les configs
sudo cp config/supervisor/idle-listeners/*.conf /etc/supervisor/conf.d/

# Recharger supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

## Configuration par Provider

### Gmail
- Supporte IMAP IDLE nativement
- Utilise OAuth2 (pas de mot de passe)
- Dossiers surveillés : INBOX, [Gmail]/Spam, [Gmail]/Promotions

### Microsoft/Outlook
- Supporte IMAP IDLE
- Utilise OAuth2
- Dossiers surveillés : INBOX, Junk Email

### Yahoo
- Supporte IMAP IDLE
- Utilise les mots de passe d'application
- Dossiers surveillés : INBOX, Bulk Mail

### IMAP Générique
- Support dépend du serveur
- La plupart des serveurs modernes supportent IDLE

## Monitoring

### Logs
```bash
# Logs du manager
tail -f storage/logs/imap-idle-manager.log

# Logs d'un listener spécifique
tail -f storage/logs/idle/imap-idle-*.log

# Logs Laravel (erreurs, debug)
tail -f storage/logs/laravel.log
```

### Vérifier les processus
```bash
# Via artisan
php artisan imap:idle-manager status

# Via supervisor
sudo supervisorctl status

# Via système
ps aux | grep "imap:idle"
```

## Dépannage

### Le listener s'arrête fréquemment
- Vérifier les credentials OAuth
- Vérifier la connexion réseau
- Augmenter la mémoire PHP si nécessaire

### Emails non détectés
- Vérifier que le compte est actif
- Vérifier le mapping des dossiers
- Vérifier les logs pour des erreurs

### Haute consommation mémoire
- Normal : ~20-50MB par connexion
- Redémarrer périodiquement si nécessaire

## Fallback

Si IMAP IDLE n'est pas disponible ou échoue, le système revient automatiquement au polling traditionnel via CRON.

## Performance

- **Détection** : 1-5 secondes (vs 5-15 minutes avec polling)
- **Ressources** : ~20-50MB RAM par compte
- **CPU** : Minimal (connexions dormantes)
- **Réseau** : Keepalive toutes les 29 minutes