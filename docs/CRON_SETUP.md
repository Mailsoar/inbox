# Configuration des Crons pour MailSoar

## Configuration Actuelle (Fonctionnelle)

Le cron utilise maintenant le binaire PHP correct en mode CLI :

```bash
* * * * * cd /home/pipi9999/inbox.mailsoar.com && /usr/local/bin/php -f artisan schedule:run >> storage/logs/cron.log 2>&1
```

## Points Importants

1. **Binaire PHP** : `/usr/local/bin/php` (mode CLI)
   - Ne PAS utiliser `/usr/bin/php` qui peut être en mode CGI
   - L'option `-f` force l'exécution du fichier

2. **Répertoire de travail** : Toujours faire `cd` vers le répertoire du projet avant d'exécuter artisan

3. **Logs** : Les logs sont écrits dans `storage/logs/cron.log`

## Tâches Planifiées

Le scheduler Laravel (`app/Console/Kernel.php`) exécute :

- **Toutes les minutes** : 
  - Traitement des emails (orchestrateur) : `emails:process-optimized`
  - Traitement de la queue des jobs : `emails:process-addresses --timeout=55`
  
- **Toutes les 30 minutes** : Rafraîchissement des tokens OAuth
  ```php
  $schedule->command('oauth:refresh-tokens')->everyThirtyMinutes();
  ```
  
- **Toutes les 20 minutes** : Vérification des connexions email

- **Toutes les heures** : Réparation des comptes Microsoft

- **Quotidien** :
  - 02h00 : Nettoyage des tests anciens
  - 03h00 : Nettoyage des métriques
  - 23h55 : Archivage des métriques

## Vérification

Pour vérifier que le cron fonctionne :

```bash
# Voir les logs du cron
tail -f storage/logs/cron.log

# Voir les logs de l'orchestrateur
tail -f storage/logs/email-processing.log

# Vérifier les tâches planifiées
php artisan schedule:list
```

## Dépannage

Si le cron ne fonctionne pas :

1. Vérifier que PHP est en mode CLI :
   ```bash
   /usr/local/bin/php -r "echo PHP_SAPI . PHP_EOL;"
   # Doit afficher : cli
   ```

2. Tester manuellement :
   ```bash
   cd /home/pipi9999/inbox.mailsoar.com
   /usr/local/bin/php artisan schedule:run
   ```

3. Vérifier les permissions :
   ```bash
   chmod -R 775 storage
   chmod -R 775 bootstrap/cache
   ```