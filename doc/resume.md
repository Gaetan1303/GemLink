# Résumé du projet StoneLink

## Objectif général
StoneLink est une plateforme web qui combine un réseau social spécialisé dans les pierres, minéraux et cristaux avec une reconnaissance visuelle assistée par intelligence artificielle.

Le projet vise à permettre aux utilisateurs de publier, découvrir, comparer et valider des pierres tout en renforçant la confiance et l’engagement communautaire.

## Principaux axes

### 1. Expérience utilisateur
- Interface claire et responsive pour mobile et desktop.
- Feed personnalisé et global avec tri par actualité et intérêts.
- Publication simple de photos et courtes vidéos de pierres.
- Profil utilisateur riche : bio, avatar, statistiques, badges et réputation.
- Partage de collections de pierres (setups) et pages publiques dédiées.

### 2. Gestion des médias
- Upload de médias sécurisé et optimisé.
- Stockage externe sur CDN pour réduire la charge serveur.
- Compression et transformation des images côté client ou intermédiaire.
- Affichage fluide avec lazy loading et placeholders.

### 3. Architecture backend
- API REST gérée par Symfony.
- Authentification sécurisée avec JWT.
- Gestion des entités principales : utilisateurs, posts, commentaires, likes, tags, setups.
- Orchestration des traitements IA et gestion des données métier.

### 4. Intelligence artificielle
- Analyse d’images de pierres pour identifier un type probable.
- Extraction d’embeddings pour recherche de similarité.
- Classification et suggestions basées sur des modèles adaptés.
- Traitement asynchrone pour éviter de bloquer l’expérience utilisateur.

### 5. Cache et performance
- Utilisation de Redis pour le cache du feed, des profils et des résultats fréquents.
- Pagination efficace et potentiellement cursor-based pour les grandes listes.
- Stockage des résultats IA et de la similarité pour limiter les recalculs.
- Des workers pour gérer les tâches lourdes et les files d’attente.

### 6. Gamification et confiance
- Système de points, niveaux et badges pour encourager l’engagement.
- Classement des utilisateurs et progression visible.
- Score de fiabilité utilisateur pour pondérer la contribution aux validations IA.
- Rôle évolutif : utilisateur → expert → modérateur.

### 7. Sécurité
- Validation stricte des fichiers uploadés.
- Limitation de la taille et de la fréquence des uploads.
- Protection CORS et contrôle des accès API.
- Stockage des médias en externe pour réduire les risques.

## Pistes de conception

### Phase 1 : MVP fonctionnel
- Authentification, profils et gestion d’utilisateurs.
- Création et affichage de posts avec médias.
- Likes, commentaires et flux principal.
- Infrastructure backend de base et base de données PostgreSQL.

### Phase 2 : IA et recherche
- Intégration d’un service IA pour classification de pierres.
- Extraction et stockage d’embeddings.
- Recherche de similarité et suggestions de posts similaires.
- Mise en place d’un traitement asynchrone.

### Phase 3 : optimisation et UX
- Ajout de Redis pour le cache et l’optimisation du feed.
- Amélioration de l’UX : skeleton loaders, pagination infinie, lazy loading.
- Mise en place de la gestion des collections de pierres.

### Phase 4 : gamification et modération
- Système de points, niveaux et badges.
- Dashboard de modération et signalement de contenu.
- Trust system pour les validations IA.
- Contrôle des rôles et permissions.

## Choix techniques recommandés
- Frontend : Angular pour une application SPA robuste.
- Backend : Symfony pour une API structurée et sécurisée.
- Base de données : PostgreSQL pour les données relationnelles.
- Cache : Redis pour les performances et les files d’attente.
- Stockage médias : Cloudinary ou Amazon S3.
- IA : service conteneurisé via Docker avec gestion asynchrone.

## Résumé
StoneLink doit rester simple au départ, avec un MVP basé sur des fonctionnalités sociales classiques, puis enrichi progressivement avec l’IA, la gamification et la modération.

Le projet doit valoriser :
- l’expérience utilisateur,
- la performance,
- la scalabilité,
- la cohérence métier,
- la séparation claire des responsabilités entre frontend, backend, IA et cache.
