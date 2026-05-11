# Cahier des charges — StoneLink V1
Plateforme de reconnaissance et partage de pierres

## Table des matières
1. [Synopsis et Vision](#synopsis-et-vision)
   - [Contexte et Concept](#contexte-et-concept)
   - [Objectifs et Enjeux](#objectifs-et-enjeux)
2. [Glossaire](#glossaire)
3. [Spécifications fonctionnelles (par epics)](#spécifications-fonctionnelles-par-epics)
   - [Epic 1 : Authentification et gestion des profils](#epic-1-authentification-et-gestion-des-profils)
   - [Epic 2 : Réseau social — Posts, Feed et Interactions](#epic-2-réseau-social--posts-feed-et-interactions)
   - [Epic 3 : Reconnaissance IA](#epic-3-reconnaissance-ia)
   - [Epic 4 : Setups de pierres (Collections)](#epic-4-setups-de-pierres-collections)
   - [Epic 5 : Gamification et Trust System](#epic-5-gamification-et-trust-system)
   - [Epic 6 : Modération et Administration](#epic-6-modération-et-administration)
4. [Spécifications techniques](#spécifications-techniques)
   - [Stack technique](#stack-technique)
   - [Dépendances et versions](#dépendances-et-versions)
   - [Architecture IA (Pipeline)](#architecture-ia-pipeline)
   - [Sécurité & Performance](#sécurité--performance)
   - [Architecture API (Endpoints)](#architecture-api-endpoints)
5. [Annexes](#annexes)

---

## 1. Synopsis et Vision

### Contexte et Concept
Le projet **StoneLink** est une plateforme web combinant un **réseau social spécialisé** dans les pierres et minéraux avec une **reconnaissance visuelle assistée par intelligence artificielle**.

La plateforme permet aux utilisateurs de photographier une pierre, d'obtenir une identification automatique, de partager leur découverte et d'interagir avec une communauté de passionnés.

Le projet se distingue par un **système de confiance communautaire** (Trust Score) qui améliore progressivement les modèles IA grâce aux validations des utilisateurs les plus fiables.

### Objectifs et Enjeux
La plateforme articule les interactions entre quatre profils d'utilisateurs :
- Grand Public curieux
- Collectionneurs passionnés
- Experts minéralogistes
- Modérateurs / Administrateurs

#### Fonctionnalités clés
- Reconnaissance IA d'une image de pierre
- Publication et partage de posts avec médias
- Validation communautaire des identifications
- Collections publiques de pierres (Setups) avec lien et QR code
- Système de réputation : points, badges, niveaux, Trust Score

#### Mission
Valoriser la passion minéralogique en combinant l'accessibilité d'un réseau social avec la précision d'un outil IA entraîné par la communauté.

### Phases de développement
- **Phase 1 – MVP Social** : Authentification, profils, posts, feed, interactions.
- **Phase 2 – IA & Recherche** : Pipeline IA, embeddings, recherche de similarité, traitement asynchrone.
- **Phase 3 – Performance & UX** : Redis, pagination infinie, lazy loading, Setups.
- **Phase 4 – Gamification & Modération** : Points, niveaux, badges, Trust System, dashboard de modération.

---

## 2. Glossaire

- **Trust Score** : score de fiabilité d'un utilisateur, calculé sur la précision historique des validations. Pondère la contribution au fine-tuning IA.
- **Pipeline IA** : chaîne de traitement d'une image : YOLO → ViT → CLIP.
- **Embedding** : représentation vectorielle d'une image de pierre, utilisée pour la recherche de similarité.
- **Cluster** : groupe de posts regroupés par similarité d'embeddings.
- **Stone (catalogue)** : entrée du catalogue officiel de minéraux, avec propriétés scientifiques.
- **Post** : publication contenant une ou plusieurs images/vidéos, description, tags et résultat IA.
- **Setup** : collection de pierres créée par un utilisateur, accessible via page publique et QR code.
- **Badge** : récompense automatique pour engagement ou expertise.
- **Validation IA** : confirmation ou correction d'une identification IA par un utilisateur.
- **Fine-tuning** : ré-entraînement périodique du modèle ViT avec des données validées.
- **Feed** : flux de posts global ou personnalisé.
- **Ticket** : signalement d'un contenu inapproprié ou d'une identification incorrecte.
- **Rôle** : niveau d'autorisation : user, expert, moderator, admin.
- **Full-IA / Full-Community** : classification du score de confiance selon qu'une identification a été validée par la communauté.

---

## 3. Spécifications fonctionnelles (par epics)

### Epic 1 : Authentification et gestion des profils

**Objectif** : permettre à chaque utilisateur de créer un compte, gérer son profil et accéder à la plateforme de manière sécurisée.

#### US : Inscription et connexion (MVP)
- Formulaire d'inscription : pseudo, email, mot de passe.
- Validation email obligatoire avant accès.
- Connexion avec email et mot de passe.
- Retour d'un JWT et d'un refresh token.
- Déconnexion et révocation du token côté serveur.
- Réinitialisation de mot de passe par email.

#### US : Gestion du profil utilisateur (MVP)
- Modification du profil : bio, pseudo, avatar (JPG/PNG, max 2 Mo).
- Profil public affichant statistiques : posts, Trust Score, niveau, badges.
- Affichage des posts, Setups et validations IA du profil.
- Consultation du profil public d'un autre utilisateur.

#### US : Gestion des rôles et élévation (Phase 4)
- Élévation automatique au rôle Expert avec seuil de Trust Score et validation Admin.
- Attribution / retrait des rôles Expert et Moderator par Admin.
- Rôle Admin attribuable uniquement par un Admin existant.

### Epic 2 : Réseau social — Posts, Feed et Interactions

**Objectif** : permettre aux utilisateurs de partager leurs découvertes, découvrir du contenu et interagir.

#### US : Création d'un post (MVP)
- Upload image (JPG/PNG/WEBP, max 10 Mo) ou vidéo courte (MP4, max 60 s, max 100 Mo).
- Ajout de titre, description et tags manuels.
- Ajout de tags IA automatiquement après analyse.
- Analyse IA déclenchée de manière asynchrone.
- Indicateur "Analyse en cours" affiché.
- Suppression de son propre post et du média CDN.

#### US : Consultation du feed (MVP)
- Feed global paginé (cursor-based, 20 posts/page).
- Feed personnalisé selon tags d'intérêt et utilisateurs suivis.
- Filtres : type de pierre, tag, niveau de confiance IA.
- Lazy loading et skeleton loaders.

#### US : Likes et commentaires (MVP)
- Like toggle sur un post.
- Commenter un post, supprimer ses commentaires.
- Notifications à l'auteur du post.

#### US : Validation IA communautaire (Phase 4)
- Bandeau d'identification IA sur les posts avec boutons de confirmation/correction.
- Validation pondérée par Trust Score.
- Identification mise à jour après seuil de validations.
- Validation incluse au dataset de fine-tuning selon seuil Trust Score.

### Epic 3 : Reconnaissance IA

**Objectif** : fournir une analyse automatique des images de pierres via un pipeline IA isolé et asynchrone.

#### US : Analyse automatique d'une image (Phase 2)
- Upload envoyé au service IA via file d'attente Redis.
- Analyse traitée de manière asynchrone.
- YOLO détecte et isole la pierre.
- ViT classifie le type de minéral et retourne un label + score de confiance.
- CLIP génère un embedding vectoriel stocké avec pgvector.
- Résultat affiché sur le post et notification envoyée.

#### US : Recherche de pierres similaires (Phase 2)
- Section "Pierres similaires" sur chaque post.
- Affichage des 5 posts les plus proches par similarité d'embedding.
- Résultats pré-calculés et mis en cache Redis pour les posts populaires.

#### US : Fine-tuning communautaire (Phase 4)
- Dashboard Admin montre les validations disponibles pour fine-tuning.
- Inclusion des validations uniquement si Trust Score suffisant.
- Versionnage des modèles et possibilité de rollback.

### Epic 4 : Setups de pierres (Collections)

**Objectif** : permettre la création et le partage de collections de pierres avec une page publique.

#### US : Création d'un Setup (Phase 3)
- Création d'un Setup avec titre, description et ajout de pierres depuis ses posts ou le catalogue.
- URL unique (slug) et page publique consultable sans connexion.
- QR code généré et téléchargeable en PNG.
- Compteur de vues sur la page publique.
- Réorganisation des pierres par glisser-déposer.

### Epic 5 : Gamification et Trust System

**Objectif** : encourager l'engagement, récompenser l'expertise et construire un système de confiance.

#### US : Système de points et niveaux (Phase 4)
- Points pour actions : post +10, like reçu +2, validation +5, validation confirmée +15.
- Niveaux basés sur des paliers définis par l'Admin.
- Profil affiche niveau et progression.

#### US : Badges (Phase 4)
- Attribution automatique de badges pour réalisations.
- Notification à l'obtention d'un badge.
- Affichage des badges sur le profil public.

#### US : Leaderboard (Phase 4)
- Top 50 des utilisateurs par points.
- Cache Redis mis à jour toutes les heures.
- Affichage de la position de l'utilisateur hors Top 50.

#### US : Trust Score (Phase 4)
- Calcul du Trust Score sur les validations confirmées vs invalidées.
- Affichage sur le profil sous forme de valeur et barre.
- Seuils configurables pour inclusion au dataset de fine-tuning.

### Epic 6 : Modération et Administration

**Objectif** : assurer la qualité et la sécurité du contenu, gérer les utilisateurs et superviser la plateforme.

#### US : Signalement de contenu (Phase 4)
- Signalement d'un post avec motif.
- Confirmation de l'envoi du signalement.
- Masquage automatique d'un post après 5 signalements.

#### US : Traitement des signalements (Phase 4)
- Dashboard des signalements pour les modérateurs.
- Acceptation ou rejet des signalements.
- Notification de la décision à l'auteur.

#### US : Supervision globale (Admin) (Phase 4)
- Suppression de posts, commentaires et comptes.
- Bannissement temporaire ou définitif.
- Dashboard KPIs : posts publiés, analyses IA, répartition des types de pierres, utilisateurs actifs.
- Gestion du catalogue Stone.
- Configuration des seuils de Trust Score, validation et paliers.

---

## 4. Spécifications techniques

### Stack technique

| Côté | Technologie | Usage |
|---|---|---|
| Frontend | Angular | SPA, routing, composants, gestion d'état réactive |
| Backend | Symfony | API REST, logique métier, JWT, Doctrine, Messenger |
| Base de données | PostgreSQL | Données relationnelles + pgvector pour embeddings |
| Cache & queues | Redis | Cache feed, leaderboard, sessions, file IA |
| Stockage médias | Cloudinary / S3 | CDN externe, transformation des médias |
| Service IA | FastAPI / Docker | Pipeline IA isolé, service d'analyse audio/image |

### Dépendances et versions
Toutes les dépendances doivent être utilisées dans leur version stable la plus récente.

#### Backend (Symfony / PHP)
- `symfony/framework-bundle` : framework principal.
- `lexik/jwt-authentication-bundle` : authentification JWT.
- `doctrine/orm` : ORM et migrations.
- `pgvector/pgvector` : extension PostgreSQL pour embeddings.
- `symfony/messenger` : files d'attente asynchrones.
- `symfony/mailer` : emails transactionnels.
- `vich/uploader-bundle` : gestion des uploads.
- `nelmio/cors-bundle` : configuration CORS.
- `snc/redis-bundle` : intégration Redis.
- `endroid/qr-code` : génération de QR codes.

#### Frontend (Angular / TypeScript)
- `@angular/core` : framework principal.
- `@ngrx/store` : gestion de l'état global.
- `@angular/material` : composants UI.
- `tailwindcss` : design utilitaire.
- `@angular/forms` : formulaires réactifs.
- `rxjs` : flux asynchrones.
- `ngx-infinite-scroll` : scroll infini.
- `lucide-angular` : icônes.
- `qrcode` : génération de QR codes côté client.

#### Service IA (Python / FastAPI)
- `fastapi` : API légère.
- `ultralytics` (YOLOv8) : détection d'objets.
- `transformers` (ViT) : classification de minéraux.
- `openai/clip` : extraction d'embeddings.
- `pillow` : traitement d'images.
- `celery` : workers asynchrones.

### Architecture IA (Pipeline)
Le service IA est isolé dans un conteneur Docker et communique avec Symfony via Redis Messenger.

| Étape | Modèle | Rôle | Sortie |
|---|---|---|---|
| Détection | YOLO v8 | Détecte et isole la pierre dans l'image | Images croppées |
| Classification | ViT | Identifie le type de minéral et score de confiance | label + confidence |
| Embedding | CLIP | Génère un vecteur pour la similarité | vector[512] -> pgvector |
| Résultat | — | Symfony enregistre le résultat et notifie l'utilisateur | post mis à jour |

Le modèle ViT est versionné et chaque version peut être rollbackée.

### Sécurité & Performance
- **Authentification JWT** : token 15 min, refresh 7 jours, cookie httpOnly, révocation via Redis.
- **Mots de passe** : critères OWASP, hachage Argon2id.
- **Validation des uploads** : type MIME, scan antivirus, taille max métrique, stockage externe.
- **RBAC** : routes protégées avec rôle minimal requis.
- **CORS** : whitelist des origines autorisées.
- **Rate limiting** : IP et utilisateur, ex. 100 requêtes/min, 10 uploads/heure.
- **SQL sécurisé** : requêtes préparées via Doctrine, pas de concaténation SQL.
- **Performance cible** : API < 200ms hors IA, IA asynchrone < 5s, Lighthouse > 90.
- **Cache** : feed, profils, leaderboard en Redis.

### Architecture API (Endpoints)
Routes prévisionnelles préfixées par `/api/v1`.

#### Authentification
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Inscription, validation email obligatoire |
| POST | `/auth/login` | Public | Connexion, retourne JWT + refresh token |
| POST | `/auth/logout` | Auth | Déconnexion et révocation du token |
| POST | `/auth/refresh` | Public | Renouvellement du JWT via refresh token |
| POST | `/auth/password/reset` | Public | Réinitialisation de mot de passe |

#### Profils utilisateur
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| GET | `/users/me` | Auth | Informations de l'utilisateur connecté |
| PUT | `/users/me` | Auth | Met à jour le profil personnel |
| GET | `/users/{id}` | Public | Profil public d'un utilisateur |
| GET | `/users/me/notifications` | Auth | Notifications de l'utilisateur |
| PATCH | `/users/me/notifications/read-all` | Auth | Marquer toutes les notifications comme lues |

#### Posts & Feed
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| GET | `/posts` | Public | Feed global paginé, filtres, cache Redis |
| GET | `/posts/feed` | Auth | Feed personnalisé, cache Redis par utilisateur |
| GET | `/posts/{id}` | Public | Détails du post, média, IA, commentaires |
| POST | `/posts` | Auth | Création de post, upload média, déclenche IA |
| DELETE | `/posts/{id}` | Auth | Suppression du post (auteur/Admin) |
| POST | `/posts/{id}/like` | Auth | Like toggle |
| POST | `/posts/{id}/comments` | Auth | Ajouter un commentaire |
| POST | `/posts/{id}/validate` | Auth | Valider/corriger l'identification IA |
| GET | `/posts/{id}/similar` | Public | Posts similaires par embedding |

#### Setups (Collections)
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| GET | `/setups/{slug}` | Public | Page publique du Setup |
| POST | `/setups` | Auth | Création de Setup avec QR code |
| PUT | `/setups/{id}` | Auth | Mise à jour du Setup |
| GET | `/setups/{id}/qrcode` | Auth | Téléchargement du QR code |

#### Gamification
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| GET | `/leaderboard` | Public | Top 50 des utilisateurs |
| GET | `/badges` | Public | Liste des badges disponibles |

#### Modération & Administration
| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| POST | `/reports` | Auth | Création d'un signalement |
| GET | `/admin/reports` | Moderator | Liste des signalements |
| PATCH | `/admin/reports/{id}/resolve` | Moderator | Résolution d'un signalement |
| GET | `/admin/users` | Admin | Liste des utilisateurs |
| PATCH | `/admin/users/{id}/role` | Admin | Modifier le rôle d'un utilisateur |
| PATCH | `/admin/users/{id}/ban` | Admin | Bannir / débannir un utilisateur |
| GET | `/admin/stats` | Admin | KPIs plateforme |
| POST | `/admin/ai/fine-tune` | Admin | Déclencher fine-tuning IA |
| GET | `/admin/ai/models` | Admin | Liste des versions IA |
| PATCH | `/admin/ai/models/{version}/activate` | Admin | Activation / rollback d'une version |

---

## 5. Annexes

### Matrice des privilèges par rôle

| Fonctionnalité | Visiteur | User | Expert | Moderator | Admin |
|---|---|---|---|---|---|
| Consulter le feed et les posts | ✓ | ✓ | ✓ | ✓ | ✓ |
| Consulter les Setups publics | ✓ | ✓ | ✓ | ✓ | ✓ |
| Créer un post | — | ✓ | ✓ | ✓ | ✓ |
| Liker / Commenter | — | ✓ | ✓ | ✓ | ✓ |
| Valider une identification IA | — | ✓ | ✓ | ✓ | ✓ |
| Créer un Setup | — | ✓ | ✓ | ✓ | ✓ |
| Signaler un contenu | — | ✓ | ✓ | ✓ | ✓ |
| Contribution pondérée au fine-tuning | — | Selon Trust Score | ✓ | ✓ | ✓ |
| Traiter les signalements | — | — | — | ✓ | ✓ |
| Supprimer tout contenu | — | — | — | ✓ | ✓ |
| Gérer les utilisateurs et rôles | — | — | — | — | ✓ |
| Bannir un utilisateur | — | — | — | — | ✓ |
| Déclencher le fine-tuning IA | — | — | — | — | ✓ |
| Gérer le catalogue Stone | — | — | — | — | ✓ |
| Consulter les KPIs & statistiques | — | — | — | — | ✓ |

### Évolutions possibles (hors scope V1)
- Application mobile native (iOS / Android)
- Scan IA en temps réel via caméra / AR
- Marketplace d'achat/vente de pierres
- Intégration avec Mindat.org
- Social graph avancé (groupes, recommandations)
- Certification Expert avec validation spécifique

