# Inbox by MailSoar

Application de test de placement d'emails permettant de vérifier où arrivent vos emails : Inbox, Spam ou Promotions.

## Fonctionnalités

### Publiques
- 🎯 **Test de placement** : Vérifiez où arrivent vos emails (inbox, spam, promotions)
- 📊 **Analyse complète** : SPF, DKIM, DMARC, détection anti-spam
- 🌍 **Multilingue** : Interface en Français et Anglais
- 📧 **Multi-providers** : Gmail, Outlook, Yahoo et autres
- 🔒 **Sécurité** : Vérification par email, rate limiting
- 📅 **Call to Action Calendly** : Intégration pour réserver des consultations d'experts
- 💬 **Support expert** : Bouton flottant pour parler à un expert en délivrabilité

### Administration
- 📈 **Dashboard complet** : Statistiques et monitoring en temps réel
- 👥 **Gestion des comptes email** : OAuth2 et IMAP
- 📊 **Métriques historiques** : Conservation et analyse des données
- 🔔 **Alertes automatiques** : Notifications en cas de problème
- 🗄️ **Archivage automatique** : Gestion intelligente de l'espace

## Technologies

- **Backend** : Laravel 11, PHP 8.2
- **Base de données** : MySQL/MariaDB
- **Frontend** : Bootstrap 5, Chart.js
- **Email** : IMAP, OAuth2 (Gmail, Outlook)
- **Authentification** : Google OAuth pour admin
- **Cache & Session** : Database driver
- **Queue** : Database driver pour jobs asynchrones

## Installation

### Prérequis

- PHP 8.2+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Node.js & NPM (optionnel, pour recompiler les assets)

### Installation locale

1. **Cloner le repository**
```bash
git clone [repository-url]
cd mailsoar-app
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurer la base de données dans `.env`**
```env
DB_HOST=localhost
DB_DATABASE=inbox_placement
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Migrations et seeders**
```bash
php artisan migrate
php artisan db:seed
```

6. **Permissions**
```bash
chmod -R 775 storage bootstrap/cache
```

7. **Lancer le serveur**
```bash
php artisan serve
```

## Configuration

### Variables d'environnement clés

```env
# Application
APP_NAME="Inbox by MailSoar"
APP_URL=https://inbox.mailsoar.com

# Limites et rétention
RATE_LIMIT_PER_EMAIL=50        # Tests par jour par email
RATE_LIMIT_PER_IP=100          # Tests par jour par IP
TEST_RETENTION_DAYS=7          # Jours avant suppression des tests
EMAIL_RETENTION_DAYS=30        # Jours avant suppression des emails
EMAIL_CHECK_TIMEOUT_MINUTES=30 # Timeout pour la réception des emails

# OAuth Google (Admin)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_ALLOWED_EMAILS=admin@example.com

# OAuth Microsoft (Comptes email)
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=

# Calendly Integration
CALENDLY_URL=https://calendly.com/pierre-mailsoar/talk-expert-test-deliverability
```

## Commandes Artisan

### Traitement des emails
```bash
# Traiter les emails en attente
php artisan emails:process

# Rafraîchir les tokens OAuth
php artisan oauth:refresh-tokens

# Vérifier les connexions
php artisan email:check-connections
```

### Maintenance
```bash
# Nettoyer les vieux tests
php artisan tests:clean [--dry-run] [--force]

# Archiver les métriques
php artisan metrics:archive [--date=YYYY-MM-DD]

# Nettoyer les métriques
php artisan metrics:clean [--days=30]
```

## Planification (Cron)

Ajouter au crontab :
```bash
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Le scheduler gère automatiquement :
- **Chaque minute** : Traitement des emails
- **Toutes les 30 min** : Refresh des tokens OAuth
- **Toutes les 20 min** : Vérification des connexions
- **23h55** : Archivage des métriques du jour
- **2h00** : Nettoyage des vieux tests

## Intégrations externes

### Calendly
L'application intègre Calendly pour permettre aux utilisateurs de réserver des consultations avec des experts en délivrabilité :

- **CTA principal** : Carte visible dans les résultats de test
- **Bouton flottant** : "Talk to an expert" accessible sur toutes les pages
- **Préremplissage intelligent** : 
  - Email du créateur du test
  - Lien direct vers les résultats du test
  - Statistiques du test (taux inbox/spam)

Configuration dans `resources/views/test/results.blade.php` :
- URL Calendly : Modifiable dans la fonction `openCalendly()`
- Position : Après les statistiques principales et en bouton flottant

## Structure du projet

```
app/
├── Console/Commands/      # Commandes artisan personnalisées
├── Http/Controllers/      # Contrôleurs (public et admin)
├── Models/               # Modèles Eloquent
├── Services/             # Services métier
│   ├── TestService.php
│   ├── EmailProcessingOrchestrator.php
│   └── ImapService.php
└── Mail/                 # Classes Mail

database/
├── migrations/           # Migrations de base de données
└── seeders/             # Seeders (providers, antispam, etc.)

resources/
├── views/               # Templates Blade
│   ├── test/           # Pages publiques
│   ├── admin/          # Pages admin
│   └── layouts/        # Layouts
└── lang/               # Traductions
    ├── fr/             # Français
    └── en/             # Anglais

routes/
├── web.php             # Routes publiques
└── admin.php           # Routes admin (protégées)
```

## Sécurité

- ✅ Protection CSRF sur tous les formulaires
- ✅ Rate limiting par email et IP
- ✅ Soft delete pour préserver l'historique
- ✅ OAuth2 pour l'authentification admin
- ✅ Validation stricte des entrées
- ✅ Protection contre suppression en cascade
- ✅ Sessions sécurisées en base de données
- ✅ Tokens avec expiration automatique

## État du projet

### ✅ Fonctionnalités complétées
- Système de test complet (création, traitement, résultats)
- OAuth2 Gmail et Outlook
- Interface multilingue FR/EN
- Dashboard admin avec statistiques
- Système d'archivage et historisation
- Rate limiting et sécurité
- Détection anti-spam
- Analyse SPF/DKIM/DMARC
- Intégration Calendly pour consultations experts
- Boutons flottants d'aide et navigation

### 🚀 Améliorations futures
- API REST pour intégrations
- Webhooks pour notifications
- Export des données (CSV/PDF)
- Tests A/B comparatifs
- Analyse prédictive avec ML

## Support

Pour toute question ou problème, contacter l'équipe MailSoar.

## License

Propriétaire - MailSoar © 2025. Tous droits réservés.
