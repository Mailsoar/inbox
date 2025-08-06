# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2025-01-06

### Supprimé
- 🧹 **Nettoyage du projet** : Suppression de 40+ fichiers non utilisés
  - 15 fichiers PHP de test/debug à la racine
  - 1 script bash de test (`check_cron_remote.sh`)
  - 2 fichiers SQL temporaires
  - 3 seeders non utilisés (CompleteSetupSeeder, InitialSetupSeeder, ProductionDataSeeder)
  - 4 vues blade obsolètes
  - 1 fichier public non utilisé (`outlook-oauth-guide.php`)
  - 5 modèles archivés dans `app/Models/Archive/`
  - 2 modèles non utilisés (UserPreference, EmailFolderMapping)
  - 1 contrôleur non utilisé (TestLaPosteController)
  - 8 commandes Console non utilisées (debug et repair commands)

### Technique
- Projet nettoyé pour le versioning et la production
- Conservation uniquement des seeders essentiels pour le hard reboot de la base de données

## [1.1.0] - 2025-01-06

### Ajouté
- 📅 **Intégration Calendly** : Call to action pour réserver des consultations d'experts
  - CTA principal dans la page de résultats après les statistiques
  - Bouton flottant "Talk to an expert" à côté de "My Tests"
  - Préremplissage intelligent du formulaire Calendly avec :
    - Email du créateur du test
    - Lien direct vers les résultats
  - Support multilingue (FR/EN) pour tous les textes du CTA

- 💬 **Boutons flottants améliorés** :
  - Conteneur unifié pour l'alignement parfait
  - Style cohérent entre les boutons
  - Responsive sur mobile (empilage vertical)
  - Animation d'apparition au chargement

### Modifié
- Refactorisation du layout `app.blade.php` pour un conteneur de boutons flottants unifié
- Mise à jour des fichiers de traduction (FR/EN) avec les nouvelles clés pour le CTA

### Technique
- URL Calendly : `https://calendly.com/pierre-mailsoar/talk-expert-test-deliverability`
- Fixes CSS pour éliminer les barres de scroll horizontales dans la popup Calendly
- Z-index optimisés pour la superposition correcte des éléments

## [1.0.0] - 2025-01-01

### Initial Release
- 🎯 Système de test de placement d'emails complet
- 📊 Analyse SPF/DKIM/DMARC
- 🌍 Interface multilingue FR/EN
- 📈 Dashboard administrateur
- 🔒 Sécurité avec rate limiting et vérification par email
- 📧 Support multi-providers (Gmail, Outlook, Yahoo, etc.)
- 🗄️ Système d'archivage automatique