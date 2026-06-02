# Cahier des Charges — GemLink

---

## Table des matières

1. [Synopsis et Vision](#1-synopsis-et-vision)
2. [Glossaire Technique](#2-glossaire-technique)
3. [Spécifications Fonctionnelles — User Stories](#3-spécifications-fonctionnelles--user-stories)
   - [Epic 1 — Authentification et gestion des profils](#epic-1--authentification-et-gestion-des-profils)
   - [Epic 2 — Réseau social : posts, feed et interactions](#epic-2--réseau-social--posts-feed-et-interactions)
   - [Epic 3 — Reconnaissance IA](#epic-3--reconnaissance-ia)
   - [Epic 4 — Collections (Vitrines)](#epic-4--collections-vitrines)
   - [Epic 5 — Gamification et Trust System](#epic-5--gamification-et-trust-system)
   - [Epic 6 — Modération et Administration](#epic-6--modération-et-administration)
4. [Architecture Technique](#4-architecture-technique)
5. [Sécurité et Performance](#5-sécurité-et-performance)
6. [Architecture API — Endpoints](#6-architecture-api--endpoints)
7. [Annexes](#7-annexes)

---

## 1. Synopsis et Vision

### Contexte et concept

Le projet **GemLink** est une application web full-stack combinant un **réseau social spécialisé** dans les pierres et minéraux avec une **reconnaissance visuelle assistée par de la correspondance d'images**. La plateforme permet à tout utilisateur de photographier une pierre, d'obtenir une identification automatisée via un pipeline IA multi-modèles, de partager sa découverte sous forme de post et d'interagir avec une communauté de passionnés.

Le projet se distingue par un **système de confiance communautaire** qui améliore progressivement la fonctionnalité de reconnaissance aux validations structurées des utilisateurs les plus fiables. Ce mécanisme d'apprentissage actif  constitue le cœur différenciateur de GemLink : les données annotées par la communauté alimentent périodiquement l'entraînement de la classification, créant un cercle vertueux entre engagement social et précision de reconnaissance.

### Personas

#### Persona 1 : Grand public curieux

| Persona | Description |
|---------|-------------|
| **Nom** | Sophie, la découvreuse occasionnelle |
| **Tranche Âge** | 25-55 ans |
| **Profession** | Tout profil non spécialiste |
| **Objectifs** | Identifier rapidement une pierre trouvée lors d'une promenade ou d'un voyage |
| **Frustrations** | Difficulté à reconnaître les minéraux, informations techniques complexes |
| **Comportements** | Utilise l'application ponctuellement, prend une photo et recherche une réponse rapide |

#### Persona 2 : Collectionneur passionné

| Persona | Description |
|---------|-------------|
| **Nom** | Marc, le collectionneur |
| **Tranche Âge** | 30-65 ans |
| **Profession** | Amateur éclairé, collectionneur de minéraux |
| **Objectifs** | Cataloguer sa collection, comparer ses spécimens et échanger avec la communauté |
| **Frustrations** | Manque d'outils d'organisation et de suivi des collections |
| **Comportements** | Consulte régulièrement l'application, ajoute de nouvelles pièces et participe aux discussions |

#### Persona 3 : Expert minéralogiste

| Persona | Description |
|---------|-------------|
| **Nom** | Claire, l'experte minéralogiste |
| **Tranche Âge** | 35-70 ans |
| **Profession** | Géologue, minéralogiste ou chercheuse |
| **Objectifs** | Vérifier les identifications, enrichir la base de connaissances et partager son expertise |
| **Frustrations** | Résultats IA imprécis, manque de contrôle sur la qualité des données |
| **Comportements** | Analyse les propositions d'identification, valide les contributions et participe à l'amélioration du catalogue |

#### Persona 4 : Modérateur / Administrateur

| Persona | Description |
|---------|-------------|
| **Nom** | Julien, l'administrateur |
| **Tranche Âge** | 25-60 ans |
| **Profession** | Gestionnaire de plateforme ou administrateur |
| **Objectifs** | Assurer le bon fonctionnement de la plateforme et la qualité des contenus |
| **Frustrations** | Contenus inappropriés, gestion manuelle chronophage |
| **Comportements** | Surveille les activités, gère les rôles utilisateurs et traite les signalements |
### Fonctionnalités clés

- **Reconnaissance** : analyse d'une image de pierre via un pipeline YOLO → ViT → CLIP, retournant un label de minéral, un score de confiance et des suggestions de posts similaires par recherche vectorielle (cosine similarity via pgvector).
- **Réseau social** : publication de posts (image JPG/PNG/WEBP ou vidéo MP4), feed paginé , système de like, commentaires, de votes, et notifications temps réel.
- **Validation communautaire** : interface de confirmation ou correction de l'identification , dont les réponses sont pondérées par le Trust Score du validateur et stockées comme dataset d'entraînement candidat.
- **Collections publiques (Vitrines)** : regroupement de posts en collections accessibles via une URL canonique à slug unique et un QR code PNG téléchargeable.
- **Gamification** : système de points, niveaux, badges déclenchés par événements, leaderboard mis en cache Redis et Trust Score calculé dynamiquement.

### Mission

Valoriser la passion minéralogique en combinant l'accessibilité d'un réseau social, l'aspect collaboratif d'une communauté expert-driven et la précision d'un outil dont la qualité est maintenue et améliorée par ses propres utilisateurs.

### Phases de développement

#### Phase 1 — MVP Social `MVP`
Authentification JWT, gestion des profils, création et affichage de posts avec upload de médias, feed global paginé, likes et commentaires, infrastructure PostgreSQL de base.

#### Phase 2 — Reconnaissance & Recherche `Phase 2`
Déploiement du service de reconnaissance conteneurisé (FastAPI + Docker), pipeline YOLO → ViT → CLIP, traitement asynchrone via Symfony Messenger (transport Redis), stockage des embeddings en base (pgvector), recherche de similarité, versioning des modèles.

#### Phase 3 — Performance & UX `Phase 3`
Mise en place du cache Redis sur le feed, les profils et les résultats de similarité, pagination infinie côté client (cursor-based), lazy loading avec skeleton loaders, création des Vitrines avec génération de QR code et compteur de vues.

#### Phase 4 — Gamification & Modération `Phase 4`
Système de points et niveaux, attribution automatique de badges par événements applicatifs, leaderboard Redis, calcul et exposition du Trust Score, interface de validation communautaire, dashboard de modération, gestion des signalements, fine-tuning périodique du modèle ViT.

---

## 2. Glossaire Technique

| Terme | Définition technique |
|---|---|
| **JWT** | JSON Web Token — token d'authentification signé (RS256) transmis dans le header `Authorization: Bearer`. Durée de vie courte (15 min), renouvelé via refresh token stocké en cookie `httpOnly`. |
| **Refresh Token** | Token opaque à longue durée de vie (7 jours), stocké côté serveur (Redis) et transmis via cookie `httpOnly; Secure; SameSite=Strict`. Permet de renouveler le JWT sans ré-authentification. |
| **Pipeline IA** | Chaîne de traitement d'image séquentielle : YOLO v8 (détection et crop de la pierre) → ViT fine-tuné (classification du minéral) → CLIP (extraction d'embedding vectoriel). Exposée via une API FastAPI conteneurisée. |
| **Embedding** | Représentation vectorielle dense d'une image (vecteur de 512 dimensions généré par CLIP). Stocké dans PostgreSQL via l'extension pgvector. Permet la recherche de similarité par distance cosinus. |
| **pgvector** | Extension PostgreSQL permettant le stockage de vecteurs denses et l'exécution de requêtes de similarité (opérateur `<=>` pour cosine distance, `<->` pour L2). |
| **Cosine Similarity** | Métrique de similarité entre deux vecteurs, mesurée par l'angle qui les sépare. Valeur comprise entre -1 et 1 ; proche de 1 = très similaires. Utilisée pour la recherche de posts similaires. |
| **Trust Score** | Score de fiabilité d'un utilisateur (0–100), calculé à partir du ratio de ses validations confirmées par la communauté vs. invalidées, pondéré par l'ancienneté du compte. Utilisé pour pondérer les contributions au dataset de fine-tuning. |
| **Active Learning** | Stratégie d'entraînement ML dans laquelle le modèle s'améliore grâce à des annotations humaines sélectionnées. Ici, les validations communautaires pondérées par Trust Score constituent le corpus d'annotations. |
| **Fine-tuning** | Ré-entraînement partiel du modèle ViT sur un nouveau dataset composé des images validées par des utilisateurs à Trust Score élevé. Exécuté périodiquement avec versioning du modèle produit. |
| **Cursor-based Pagination** | Technique de pagination utilisant un identifiant opaque (cursor) pointant vers le dernier élément chargé, plutôt qu'un numéro de page. Stable lors d'insertions concurrentes et plus performante sur de grands volumes. |
| **Skeleton Loader** | Placeholder de chargement affichant la structure visuelle d'un composant avant que les données ne soient disponibles, améliorant la *perceived performance*. |
| **CDN** | Content Delivery Network — réseau de distribution géographique pour les assets statiques (images, vidéos). Réduit la latence et la charge sur le serveur applicatif. Cloudinary ou Amazon S3 + CloudFront. |
| **Slug** | Identifiant lisible par l'humain dans une URL, généré à partir du titre d'une collection (ex : `ma-collection-de-quartz`). Unique en base, utilisé comme clé de routage. |
| **RBAC** | Role-Based Access Control — contrôle d'accès basé sur les rôles. Chaque route API est protégée par un rôle minimum requis. Rôles définis : `user`, `expert`, `moderator`, `admin`. |
| **Rate Limiting** | Limitation du nombre de requêtes acceptées par IP et par utilisateur sur une fenêtre temporelle glissante, stockée dans Redis. Protège l'API contre les abus et les attaques par force brute. |
| **Webhook / Queue** | Symfony Messenger avec transport Redis. Les tâches lourdes (analyse IA, envoi d'emails, calcul de points) sont publiées dans une file de messages et consommées par des workers dédiés. |
| **Vitrine** | Terme produit désignant une collection publique de pierres créée par un utilisateur. Accessible sans authentification via une URL unique et un QR code PNG. |
| **Stone (catalogue)** | Entité du catalogue officiel de minéraux de la plateforme, avec ses propriétés scientifiques (dureté Mohs, système cristallin, composition chimique, catégorie). Référence taxonomique pour les identifications IA. |

---

# User Stories — GemLink V2

> **Version :** 2.0 — Draft  
> **Périmètre :** Spécifications fonctionnelles uniquement — sans mapping de routes API

---

## Table des matières

- [Epic 1 — Authentification et gestion des profils](#epic-1--authentification-et-gestion-des-profils)
- [Epic 2 — Réseau social : posts, feed et interactions](#epic-2--réseau-social--posts-feed-et-interactions)
- [Epic 3 — Reconnaissance IA](#epic-3--reconnaissance-ia)
- [Epic 4 — Collections (Vitrines)](#epic-4--collections-vitrines)
- [Epic 5 — Gamification et Trust System](#epic-5--gamification-et-trust-system)
- [Epic 6 — Modération et Administration](#epic-6--modération-et-administration)

---

## Epic 1 — Authentification et gestion des profils

> **Objectif :** Permettre à tout visiteur de créer un compte sécurisé, de le gérer et d'accéder à la plateforme via un système d'authentification stateless basé sur JWT et refresh token.

---

### US 1.1 — Inscription `MVP`

**En tant que** visiteur non authentifié, **je veux** pouvoir créer un compte via un formulaire d'inscription **afin de** disposer d'un profil persistant sur la plateforme et accéder aux fonctionnalités protégées.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le formulaire expose trois champs obligatoires : un pseudo (alphanumérique, 3–30 caractères), une adresse email au format RFC 5322 et un mot de passe. Tout champ manquant ou invalide empêche la soumission et affiche un message d'erreur explicite. |
| CA-2 | Le mot de passe est validé côté serveur selon la politique de sécurité : minimum 8 caractères, au moins 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial. Le hash est stocké avec l'algorithme **Argon2id**. |
| CA-3 | Si l'adresse email est déjà enregistrée en base, le système retourne un message d'erreur générique ne permettant pas l'énumération de comptes existants. |
| CA-4 | À l'issue de l'inscription, un email de confirmation est envoyé de manière asynchrone. Le compte reste en statut `PENDING_VALIDATION` tant que l'adresse email n'a pas été confirmée. |
| CA-5 | Toute tentative de connexion avec un compte en statut `PENDING_VALIDATION` est rejetée avec un message invitant l'utilisateur à vérifier sa boîte mail. |

---

### US 1.2 — Validation d'adresse email `MVP`

**En tant que** nouvel utilisateur inscrit, **je veux** recevoir un email contenant un lien de validation unique **afin de** confirmer que j'ai accès à l'adresse email renseignée à l'inscription et d'activer mon compte.

| # | Critère d'acceptation |
|---|---|
| CA-1 | L'email de validation est envoyé dans les 30 secondes suivant l'inscription via un worker asynchrone. |
| CA-2 | Le lien de validation contient un token signé à usage unique, avec une durée d'expiration de **1 heure**. Un clic sur un lien expiré affiche un message d'erreur explicite et propose de renvoyer un nouveau lien. |
| CA-3 | Un clic sur un lien valide fait passer le compte en statut `ACTIVE` et redirige l'utilisateur vers la page de connexion avec un message de confirmation. |
| CA-4 | Un bouton « Renvoyer l'email de validation » est disponible sur la page de statut du compte. Pour éviter les abus, l'envoi est limité à 3 tentatives par heure par adresse IP (rate limiting). |

---

### US 1.3 — Connexion `MVP`

**En tant qu'** utilisateur avec un compte actif, **je veux** me connecter avec mon email et mon mot de passe **afin d'** obtenir une session authentifiée me permettant d'interagir avec les fonctionnalités protégées.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Après connexion réussie, le système émet un **JWT** signé (durée de vie 15 minutes) et un **refresh token** opaque (durée de vie 7 jours). Le refresh token est transmis dans un cookie `httpOnly; Secure; SameSite=Strict` pour prévenir les attaques XSS. |
| CA-2 | En cas d'identifiants invalides, le système retourne systématiquement un message d'erreur générique, sans distinguer email inconnu de mot de passe incorrect, afin de prévenir l'énumération de comptes. |
| CA-3 | Après 5 tentatives de connexion échouées consécutives sur le même compte dans une fenêtre de 10 minutes, le système applique un délai progressif (throttling) avant d'autoriser une nouvelle tentative. |

---

### US 1.4 — Renouvellement de session `MVP`

**En tant qu'** utilisateur authentifié dont le JWT a expiré, **je veux** que mon client renouvelle automatiquement mon token d'accès via le refresh token **afin de** ne pas être déconnecté de manière intempestive au cours de ma navigation.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le renouvellement s'effectue de manière transparente côté client, sans interaction de l'utilisateur, dès que le JWT arrive en fin de vie. |
| CA-2 | À chaque renouvellement réussi, le refresh token précédent est révoqué et un nouveau est émis (rotation de refresh token), réduisant la fenêtre d'exploitation en cas de vol de token. |
| CA-3 | Si le refresh token est invalide, expiré ou révoqué, l'utilisateur est redirigé vers la page de connexion. |

---

### US 1.5 — Déconnexion `MVP`

**En tant qu'** utilisateur authentifié, **je veux** pouvoir me déconnecter explicitement **afin de** révoquer ma session et protéger mon compte, notamment sur un appareil partagé.

| # | Critère d'acceptation |
|---|---|
| CA-1 | La déconnexion révoque le refresh token en base et supprime le cookie de session côté client. |
| CA-2 | Le JWT actif est immédiatement invalidé via une blocklist Redis avec une TTL égale à sa durée de vie résiduelle. Toute requête ultérieure portant cet ancien token est rejetée. |
| CA-3 | Après déconnexion, l'utilisateur est redirigé vers la page d'accueil publique. |

---

### US 1.6 — Réinitialisation du mot de passe `MVP`

**En tant qu'** utilisateur ayant perdu l'accès à son compte, **je veux** déclencher une procédure de réinitialisation de mot de passe par email **afin de** retrouver l'accès à mon compte sans intervention d'un administrateur.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le formulaire de demande de réinitialisation accepte une adresse email. Qu'elle soit connue ou non en base, la réponse affichée est toujours identique pour éviter l'énumération de comptes. L'email de reset n'est envoyé que si l'adresse existe réellement. |
| CA-2 | Le lien de réinitialisation contient un token à usage unique, stocké de manière sécurisée en base (hashé en SHA-256), avec une TTL de **1 heure**. |
| CA-3 | La soumission du nouveau mot de passe valide le token, vérifie sa non-expiration et sa non-utilisation, puis révoque tous les refresh tokens actifs de l'utilisateur concerné pour invalider les sessions en cours. |
| CA-4 | Le nouveau mot de passe est soumis aux mêmes contraintes de sécurité que lors de l'inscription (CA-2 de US 1.1). |

---

### US 1.7 — Gestion du profil utilisateur `MVP`

**En tant qu'** utilisateur authentifié, **je veux** modifier les informations de mon profil public **afin de** représenter fidèlement mon identité et mon expertise au sein de la communauté.

| # | Critère d'acceptation |
|---|---|
| CA-1 | L'utilisateur peut modifier son pseudo, sa bio (max 500 caractères) et son avatar. Ces modifications sont immédiatement visibles sur son profil public. |
| CA-2 | L'avatar uploadé est validé côté serveur : seuls les formats `image/jpeg`, `image/png` et `image/webp` sont acceptés, avec une taille maximale de **2 Mo**. Le fichier est ensuite redimensionné automatiquement à 256×256 px (recadrage centré) avant stockage sur le CDN. |
| CA-3 | Un utilisateur ne peut modifier que son propre profil. Toute tentative de modification du profil d'un autre utilisateur est rejetée par le système. |
| CA-4 | Le profil public d'un utilisateur est accessible sans authentification. Il expose : pseudo, avatar, bio, niveau, Trust Score, badges obtenus et liste des posts publiés. |

---

## Epic 2 — Réseau social : posts, feed et interactions

> **Objectif :** Permettre la publication de contenu médiatique, la découverte via un feed performant et les interactions sociales classiques (likes, commentaires), tout en déclenchant automatiquement l'analyse IA de manière asynchrone.

---

### US 2.1 — Publication d'un post `MVP`

**En tant qu'** utilisateur authentifié, **je veux** publier un post contenant une image ou une courte vidéo de ma pierre **afin de** partager ma découverte avec la communauté et obtenir une identification automatique par l'IA.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le formulaire de publication accepte un fichier média (obligatoire), un titre (optionnel), une description (optionnelle) et des tags manuels (optionnels). Tout post sans fichier média valide est rejeté. |
| CA-2 | Les formats d'image acceptés sont `jpeg`, `png` et `webp`, avec une taille maximale de **10 Mo**. Les vidéos sont limitées au format `mp4`, d'une durée maximale de **60 secondes** et d'une taille maximale de **100 Mo**. Le type MIME est vérifié côté serveur par lecture des magic bytes, indépendamment de l'extension du fichier. |
| CA-3 | Après validation, le fichier est transféré sur le CDN externe. Le post est créé immédiatement en base avec le statut `PENDING_ANALYSIS` et une analyse IA est déclenchée en tâche de fond, sans bloquer la réponse ni l'affichage du post. |
| CA-4 | La suppression d'un post est réservée à son auteur, à un modérateur ou à un administrateur. En production, privilégier un *soft delete* (`deleted_at`) pour la traçabilité ; la purge des entités associées (embeddings, likes, commentaires) doit être gérée explicitement dans la couche métier (ex. `PostService::softDelete()`) ou, si la politique de rétention/RGPD l'exige, effectuer un `DELETE` physique qui déclenchera les `ON DELETE CASCADE`. Voir `doc/bdd.md` pour la stratégie détaillée. |

---

### US 2.2 — Consultation du feed `MVP`

**En tant que** visiteur ou utilisateur authentifié, **je veux** accéder à un flux de posts **afin de** découvrir les pierres partagées par la communauté et naviguer dans le contenu de manière fluide.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le feed est paginé en **cursor-based pagination** : l'utilisateur reçoit un curseur opaque pointant vers le dernier élément chargé, permettant de récupérer la page suivante de manière stable, même lors d'insertions concurrentes. La taille de page par défaut est de **20 posts**. |
| CA-2 | Le feed accepte des filtres : par tag, par type de minéral identifié et par score de confiance IA minimum. |
| CA-3 | Les résultats du feed global sont mis en cache (TTL 5 minutes). Pour éviter les invalidations totales et le *cache stampede*, stocker les IDs récents dans une `Redis List` (LPUSH/LTRIM) et mettre à jour le feed de façon incrémentale ; utiliser `stale-while-revalidate` et un mutex de reconstruction si nécessaire. |
| CA-4 | Un utilisateur authentifié peut accéder à un feed personnalisé, construit à partir de ses tags d'intérêt enregistrés dans son profil. |
| CA-5 | Le frontend implémente un scroll infini : lorsque l'utilisateur approche du bas de la page, la page suivante se charge automatiquement. Pendant ce chargement, des **skeleton loaders** sont affichés à la place des futures cartes de post. |
| CA-6 | Le temps de réponse du feed doit être inférieur à **200 ms** (p99) en cache chaud. |

---

### US 2.3 — Système de likes `MVP`

**En tant qu'** utilisateur authentifié, **je veux** pouvoir liker ou retirer mon like sur un post **afin d'** exprimer mon intérêt et de contribuer au score de popularité du contenu.

| # | Critère d'acceptation |
|---|---|
| CA-1 | L'action de like fonctionne en toggle : un premier clic ajoute le like, un second le retire. La contrainte d'unicité garantit qu'un utilisateur ne peut liker qu'une seule fois le même post. |
| CA-2 | Le compteur de likes est mis à jour de manière optimiste côté client (sans attendre la confirmation serveur) pour garantir une UX réactive. En cas d'erreur réseau, le compteur est rollbacké à sa valeur précédente. |
| CA-3 | L'auteur du post reçoit une notification in-app lors de la réception d'un like. Les notifications sont dédupliquées : un même utilisateur ne génère pas plusieurs notifications s'il like et unlike successivement. |

---

### US 2.4 — Commentaires `MVP`

**En tant qu'** utilisateur authentifié, **je veux** pouvoir ajouter et gérer des commentaires sous les posts **afin d'** interagir directement avec les auteurs et la communauté.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Un commentaire est limité à **1000 caractères**. Il est associé à l'utilisateur authentifié et au post cible. |
| CA-2 | Un utilisateur peut supprimer uniquement ses propres commentaires. Un modérateur ou un administrateur peut supprimer tout commentaire. La suppression est une suppression logique (soft delete) conservant une trace dans l'audit log. |
| CA-3 | Les commentaires sont affichés dans l'ordre chronologique croissant et paginés (20 par page, cursor-based). |
| CA-4 | L'auteur du post reçoit une notification in-app à chaque nouveau commentaire sur l'un de ses posts. |

---

### US 2.5 — Affichage du résultat de correspondance sur un post `Phase 2`

**En tant qu'** utilisateur consultant un post, **je veux** voir le résultat de l'analyse IA de la pierre photographiée **afin de** connaître l'identification automatique et son niveau de fiabilité.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Tant que le post est en statut `PENDING_ANALYSIS`, le composant frontend affiche un indicateur "Analyse en cours" avec un skeleton loader à la place du bloc résultat IA. |
| CA-2 | Une fois l'analyse terminée, le résultat affiché comprend : le label du minéral identifié, le score de confiance (0–100 %), un lien vers la fiche du catalogue Stone si une correspondance existe, et le badge "Validé par la communauté" si le seuil de validation est atteint. |
| CA-3 | Si le score de confiance est inférieur à un seuil configurable par l'Admin (ex : 40 %), un label "Identification incertaine" est affiché pour informer l'utilisateur des limites du modèle. |
| CA-4 | Le frontend se met à jour automatiquement dès que l'analyse est terminée, sans que l'utilisateur ait besoin de recharger la page. |

---

### US 2.6 — Posts similaires `Phase 2`

**En tant qu'** utilisateur consultant un post analysé, **je veux** voir une section de posts similaires **afin de** découvrir d'autres spécimens de la même famille minéralogique ou visuellement proches.

| # | Critère d'acceptation |
|---|---|
| CA-1 | La section "Posts similaires" affiche jusqu'à 5 posts dont l'embedding vectoriel est le plus proche de celui du post consulté, calculé par **distance cosinus** via pgvector. |
| CA-2 | Les résultats de similarité sont pré-calculés et mis en cache (TTL 1 heure) pour les posts ayant reçu plus de 10 vues, afin d'éviter des calculs vectoriels répétés sur les contenus populaires. |
| CA-3 | Un post dont l'analyse IA est en cours ou a échoué n'affiche pas de section "Posts similaires". |

---

### US 2.7 — Validation communautaire de l'identification IA `Phase 4`

**En tant qu'** utilisateur authentifié, **je veux** pouvoir confirmer ou corriger l'identification IA d'un post **afin de** contribuer à l'amélioration du modèle et d'aider les autres membres de la communauté.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Sous chaque post analysé, un composant de validation expose trois actions : **Confirmer** (le label IA est correct), **Corriger** (proposer un label alternatif via un champ avec autocomplétion sur le catalogue Stone), **Invalider** (le label est manifestement incorrect, sans proposition). |
| CA-2 | Chaque validation est enregistrée avec le snapshot du Trust Score du validateur au moment de la soumission, afin de garantir la traçabilité historique même en cas d'évolution ultérieure du score. |
| CA-3 | La contribution d'une validation au score de consensus est pondérée par le Trust Score du validateur : un utilisateur avec un Trust Score de 80 pèse 4× plus qu'un utilisateur à 20 dans le calcul du consensus. |
| CA-4 | Lorsque le score de consensus pondéré dépasse un seuil configurable par l'Admin, l'identification du post est mise à jour avec le label majoritaire et taguée `COMMUNITY_VALIDATED`. |
| CA-5 | Les validations issues d'utilisateurs dont le Trust Score dépasse le seuil défini par l'Admin sont automatiquement ajoutées au dataset candidat pour le prochain cycle de fine-tuning IA. |

---

## Epic 3 — Reconnaissance

> **Objectif :** Fournir une analyse automatique et asynchrone des images de pierres via un pipeline IA !multi-modèles isolé dans un service conteneurisé, avec stockage des résultats en base pour éviter tout recalcul.

---

### US 3.1 — Déclenchement asynchrone de l'analyse `Phase 2`

**En tant que** plateforme, **je veux** analyser automatiquement chaque image de pierre uploadée **afin de** fournir une identification sans bloquer l'expérience utilisateur.

| # | Critère d'acceptation |
|---|---|
| CA-1 | À la création d'un post, un message est publié dans la file de tâches asynchrones (Symfony Messenger, transport Redis) pour déclencher l'analyse IA sans bloquer la réponse serveur. |
| CA-2 | Le worker consommateur appelle le service IA (FastAPI conteneurisé) en lui transmettant l'URL CDN du média. Le service retourne un objet structuré contenant le label, le score de confiance et l'embedding vectoriel. |
| CA-3 | En cas d'échec de l'appel au service IA (timeout, erreur serveur), le message est remis en file avec un mécanisme de **retry exponentiel** (3 tentatives, délais 30 s, 2 min, 10 min). Après 3 échecs consécutifs, le post passe en statut `ANALYSIS_FAILED` et une alerte est envoyée à l'Admin. |
| CA-4 | Les résultats de l'analyse (label, score de confiance, embedding vectoriel float32[512]) sont persistés en base. L'embedding est stocké via le type `vector(512)` de l'extension **pgvector**. |

---

### US 3.2 — Pipeline de classification `Phase 2`

**En tant que** service IA, **je veux** traiter les images via un pipeline séquentiel YOLO → ViT → CLIP **afin de** produire une identification précise du type de minéral et un embedding exploitable pour la recherche de similarité.

| # | Critère d'acceptation |
|---|---|
| CA-1 | **Étape 1 — Détection (YOLO v8) :** Le modèle détecte et localise la ou les pierre(s) dans l'image source. Si plusieurs objets sont détectés avec un score de confiance supérieur à 0.5, chaque zone est croppée individuellement pour être soumise aux étapes suivantes. |
| CA-2 | **Étape 2 — Classification (ViT fine-tuné) :** Le modèle Vision Transformer classifie le type de minéral sur chaque crop et retourne un label parmi les classes du catalogue ainsi qu'un score de confiance normalisé (0–1, score softmax). |
| CA-3 | **Étape 3 — Embedding (CLIP) :** Le modèle CLIP génère un vecteur de **512 dimensions** pour chaque image croppée. Ce vecteur est normalisé (norme L2 = 1) pour permettre un calcul de similarité cosinus efficace lors des requêtes pgvector. |
| CA-4 | Le résultat du pipeline inclut un champ `model_version` permettant de tracer quelle version de chaque modèle a produit le résultat, facilitant le diagnostic en cas de régression de performance après fine-tuning. |

---

### US 3.3 — entraînement de la reconnaissance via la communauté `Phase 4`

**En tant qu'** administrateur, **je veux** déclencher périodiquement un cycle de fine-tuning du modèle ViT à partir des validations communautaires de qualité **afin d'** améliorer progressivement la précision des identifications.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Un tableau de bord Admin affiche le nombre de nouvelles validations disponibles pour le fine-tuning, le seuil de Trust Score configuré et la date du dernier cycle exécuté. |
| CA-2 | Seules les validations d'utilisateurs dont le Trust Score est au-dessus du seuil configuré sont incluses dans le dataset d'entraînement candidat, garantissant la qualité des annotations. |
| CA-3 | Chaque cycle de fine-tuning produit une nouvelle version versionée du modèle (ex : `vit-v1.2.0`). L'Admin peut activer une version antérieure en cas de dégradation des performances mesurées (rollback). |
| CA-4 | La progression d'un cycle de fine-tuning en cours est consultable en temps réel depuis le dashboard Admin (statut, pourcentage d'avancement, logs). |

---

## Epic 4 — Collections (Vitrines)

> **Objectif :** Permettre la création de collections organisées de pierres, accessibles publiquement sans authentification via une URL canonique et un QR code, avec suivi du nombre de vues.

---

### US 4.1 — Création et gestion d'une Vitrine `Phase 3`

**En tant qu'** utilisateur authentifié, **je veux** créer une collection regroupant plusieurs de mes posts **afin de** présenter mes pierres de manière organisée et de la partager facilement.

| # | Critère d'acceptation |
|---|---|
| CA-1 | La création d'une Vitrine requiert un titre obligatoire (max 100 caractères) et accepte une description optionnelle (max 500 caractères). Un slug URL est généré automatiquement à partir du titre (lowercase, tirets à la place des espaces, suppression des caractères spéciaux). En cas de collision, un suffixe numérique est ajouté automatiquement. |
| CA-2 | Des posts peuvent être ajoutés à la Vitrine individuellement. Chaque item de la collection stocke une position permettant le tri ordonné. |
| CA-3 | L'ordre des items peut être modifié par glisser-déposer depuis l'interface de gestion. |
| CA-4 | Une Vitrine ne peut pas être publiée si elle ne contient aucun item. Le système affiche un message explicite dans ce cas. |

---

### US 4.2 — Page publique et QR code `Phase 3`

**En tant que** visiteur, **je veux** accéder à la page publique d'une Vitrine sans authentification **afin de** consulter la collection partagée par un membre de la communauté.

| # | Critère d'acceptation |
|---|---|
| CA-1 | La page publique d'une Vitrine est accessible via son URL canonique (slug) sans authentification. Elle expose le titre, la description, le profil du créateur et la liste ordonnée des posts avec leurs résultats IA. |
| CA-2 | Chaque consultation de la page publique incrémente un compteur de vues. Pour éviter les écritures synchrones à chaque visite, l'incrément est bufférisé en mémoire (Redis) et persisté en base toutes les 60 secondes par un worker périodique. |
| CA-3 | Un QR code PNG pointant vers l'URL publique de la Vitrine est généré automatiquement à la création et stocké sur le CDN. Il est téléchargeable depuis l'interface de gestion de la Vitrine. |

---

## Epic 5 — Gamification et Trust System

> **Objectif :** Motiver l'engagement des utilisateurs via un système de progression transparent (points, niveaux, badges), calculer un Trust Score fiable pour pondérer les contributions IA et exposer un leaderboard performant.

---

### US 5.1 — Système de points `Phase 4`

**En tant qu'** utilisateur authentifié, **je veux** gagner des points pour chacune de mes actions sur la plateforme **afin de** voir ma progression et d'être récompensé pour ma contribution à la communauté.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Les événements générateurs de points sont définis dans la configuration applicative. Barème initial : publication d'un post (+10 pts), réception d'un like (+2 pts), validation soumise (+5 pts), validation confirmée par consensus (+15 pts). Ce barème est modifiable par l'Admin sans déploiement. |
| CA-2 | L'attribution de points est réalisée de manière asynchrone (tâche de fond) afin de ne pas bloquer la réponse des actions déclencheuses. |
| CA-3 | Le total de points et l'historique détaillé des transactions de points (action, montant, date) sont consultables depuis le profil de l'utilisateur. |

---

### US 5.2 — Niveaux et progression `Phase 4`

**En tant qu'** utilisateur, **je veux** progresser en niveaux à mesure que j'accumule des points **afin de** visualiser mon expertise et ma séniorité sur la plateforme.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Les paliers de niveau sont définis en base et administrables par l'Admin sans déploiement : Niveau 1 "Novice" (0–99 pts), Niveau 2 "Initié" (100–499 pts), Niveau 3 "Connaisseur" (500–1999 pts), Niveau 4 "Expert" (2000–9999 pts), Niveau 5 "Maître" (10 000+ pts). |
| CA-2 | Le niveau courant et le pourcentage de progression vers le niveau suivant sont calculés dynamiquement et affichés sur le profil de l'utilisateur sous forme de barre de progression. |
| CA-3 | Un passage de niveau déclenche une notification in-app et peut déclencher l'attribution d'un badge si un badge est associé au nouveau niveau. |

---

### US 5.3 — Badges `Phase 4`

**En tant qu'** utilisateur, **je veux** recevoir des badges automatiquement pour mes accomplissements **afin de** valoriser mes contributions spécifiques sur mon profil public.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Les conditions d'attribution des badges sont définies en base par l'Admin (type de condition, valeur seuil). Exemples : "Premier Post" au premier post publié, "Expert Quartz" après 10 identifications de quartz confirmées. L'évaluation est déclenchée automatiquement par les événements applicatifs concernés. |
| CA-2 | Un badge est attribué une seule fois par utilisateur. Une tentative d'attribution en double est ignorée silencieusement. |
| CA-3 | L'attribution d'un badge déclenche une notification in-app affichant le nom et la description du badge obtenu. |
| CA-4 | Les badges obtenus sont affichés sur le profil public de l'utilisateur. |

---

### US 5.4 — Leaderboard `Phase 4`

**En tant qu'** utilisateur, **je veux** consulter le classement des membres les plus actifs **afin de** me situer par rapport à la communauté et d'identifier les experts de référence.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le leaderboard est stocké dans un **Sorted Set Redis** mis à jour en temps réel à chaque attribution de points. Il retourne les 50 premiers utilisateurs triés par points décroissants. |
| CA-2 | Un utilisateur authentifié peut consulter sa propre position dans le classement général, même s'il ne figure pas dans le Top 50. |
| CA-3 | Une synchronisation complète depuis la base de données est exécutée quotidiennement par un cron job pour corriger d'éventuelles dérives entre Redis et PostgreSQL. |

---

### US 5.5 — Trust Score `Phase 4`

**En tant que** plateforme, **je veux** calculer un score de fiabilité pour chaque utilisateur **afin de** pondérer la valeur de ses contributions au consensus de validation et au dataset de fine-tuning IA.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le Trust Score est un entier entre 0 et 100, recalculé automatiquement à chaque fois qu'une validation de l'utilisateur est confirmée ou invalidée par le consensus communautaire. La formule de base est : `trust_score = (validations_confirmées / total_validations) × 100`, pondérée par un facteur d'ancienneté croissant avec le volume de validations totales. |
| CA-2 | Le Trust Score est affiché sur le profil public de l'utilisateur. Il n'est jamais modifiable manuellement, même par un administrateur. |
| CA-3 | L'Admin configure depuis le dashboard le seuil minimum de Trust Score en dessous duquel les validations ne sont pas incluses dans le dataset de fine-tuning candidat. |

---

## Epic 6 — Modération et Administration

> **Objectif :** Garantir la sécurité et la qualité du contenu via un workflow de signalement structuré, permettre à l'Admin de superviser l'ensemble de la plateforme et de piloter les cycles de fine-tuning IA.

---

### US 6.1 — Signalement de contenu `Phase 4`

**En tant qu'** utilisateur authentifié, **je veux** signaler un contenu inapproprié **afin de** contribuer à la sécurité et à la qualité de la plateforme.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le formulaire de signalement requiert la sélection d'un motif parmi une liste prédéfinie : `Contenu inapproprié`, `Identification manifestement incorrecte`, `Spam`, `Harcèlement`. Une description libre optionnelle peut être ajoutée. |
| CA-2 | Un utilisateur ne peut soumettre qu'un seul signalement par post. Une tentative de double signalement affiche un message l'informant que son signalement a déjà été pris en compte. |
| CA-3 | Lorsqu'un post atteint un seuil de **5 signalements**, il passe automatiquement en statut `AUTO_HIDDEN` (masqué du feed public) et entre dans la file de traitement des modérateurs. |

---

### US 6.2 — Traitement des signalements `Phase 4`

**En tant que** modérateur, **je veux** traiter les signalements depuis un dashboard dédié **afin de** maintenir la qualité du contenu et d'exercer une modération transparente et tracée.

| # | Critère d'acceptation |
|---|---|
| CA-1 | Le dashboard de modération liste les signalements en attente, triés par nombre décroissant de signalements, avec le détail de chaque motif et l'historique de modération du post concerné. |
| CA-2 | Pour chaque signalement, le modérateur peut choisir entre **Accepter** (le post est supprimé en soft delete et l'auteur est notifié) ou **Rejeter** (le post est restauré en statut `PUBLISHED` s'il était `AUTO_HIDDEN`). |
| CA-3 | Chaque action de modération est tracée dans un **audit log immuable** (aucun UPDATE ni DELETE autorisé sur cette table) contenant : identifiant du modérateur, action effectuée, cible, motif et horodatage. |

---

### US 6.3 — Gestion administrative `Phase 4`

**En tant qu'** administrateur, **je veux** disposer d'un contrôle total sur les utilisateurs, les contenus et les paramètres de la plateforme **afin d'** assurer le bon fonctionnement opérationnel de GemLink.

| # | Critère d'acceptation |
|---|---|
| CA-1 | L'Admin peut modifier le rôle d'un utilisateur parmi `user`, `expert` et `moderator`. Le rôle `admin` ne peut être attribué que par un autre `admin` et ne peut jamais être rétrogradé via l'interface standard. |
| CA-2 | L'Admin peut bannir temporairement ou définitivement un utilisateur en précisant un motif. Le bannissement révoque immédiatement tous ses refresh tokens actifs, l'empêchant de maintenir ou de renouveler sa session. |
| CA-3 | Le dashboard Admin affiche les KPIs suivants, mis à jour régulièrement : nombre de posts/24h, nombre d'analyses IA/24h, répartition des minéraux identifiés (top 10), nombre d'utilisateurs actifs sur 7 jours, taux de validation communautaire. |
| CA-4 | L'Admin peut déclencher un cycle de fine-tuning asynchrone en spécifiant le seuil minimum de Trust Score et le nom de la version du modèle à produire. La progression est consultable en temps réel depuis le dashboard. |
| CA-5 | L'Admin peut consulter la liste des versions du modèle ViT avec leurs métriques de performance (accuracy, F1-score sur le dataset de validation) et activer une version antérieure en cas de régression (rollback). |

---

## 4. Architecture Technique

### Stack et responsabilités

| Couche | Technologie | Rôle |
|---|---|---|
| **Frontend** | Angular (SPA) | Interface utilisateur, routing côté client, gestion d'état (NgRx/RxJS), communication avec l'API REST via HttpClient. |
| **Backend** | Symfony (PHP) | API REST, logique métier, authentification JWT, orchestration des tâches asynchrones via Symfony Messenger, communication avec le service IA. |
| **Base de données** | PostgreSQL | Stockage relationnel de toutes les entités. Extension **pgvector** pour le stockage et la requête des embeddings vectoriels. |
| **Cache & Queues** | Redis | Cache des feeds, des profils et des résultats de similarité. Sorted Set pour le leaderboard. Transport des messages Symfony Messenger. Blocklist JWT. |
| **Stockage médias** | Cloudinary / S3 | CDN externe pour les images et vidéos. Transformation à la volée (redimensionnement, compression) côté CDN. |
| **Service IA** | FastAPI (Python) | Service isolé exposant le pipeline YOLO → ViT → CLIP. Déployé dans un conteneur Docker. Communication synchrone avec Symfony via HTTP. |

### Dépendances Backend (Symfony / PHP)

| Bibliothèque | Catégorie | Justification |
|---|---|---|
| `lexik/jwt-authentication-bundle` | Authentification | Génération et validation des JWT (RS256). |
| `doctrine/orm` | ORM | Mapping objet-relationnel, migrations via Doctrine Migrations. |
| `pgvector/pgvector` (extension PG) | Vectors | Stockage de vecteurs `vector(512)` et requêtes de similarité cosinus. |
| `symfony/messenger` | Queues | Publication et consommation de messages asynchrones (transport Redis). |
| `symfony/mailer` | Emailing | Envoi des emails transactionnels (validation, reset, notifications). |
| `vich/uploader-bundle` | Upload | Gestion des uploads `multipart/form-data` avec validation MIME et taille. |
| `nelmio/cors-bundle` | Sécurité | Configuration de la politique CORS (whitelist d'origines). |
| `snc/redis-bundle` | Cache | Intégration Redis pour le cache applicatif et les sessions. |
| `endroid/qr-code` | QR Code | Génération des QR codes PNG pour les Vitrines. |

### Dépendances Frontend (Angular / TypeScript)

| Bibliothèque | Catégorie | Justification |
|---|---|---|
| `@ngrx/store` + `@ngrx/effects` | État | Gestion de l'état global (flux de données unidirectionnel, side effects asynchrones). |
| `@angular/material` | UI | Composants accessibles (WCAG 2.1 AA) : dialogs, snackbars, form fields. |
| `tailwindcss` | Style | Framework CSS utilitaire pour un design responsive cohérent. |
| `rxjs` | Réactivité | Gestion des flux de données asynchrones (Observables, opérateurs). |
| `ngx-infinite-scroll` | UX | Directive de scroll infini pour le feed (déclenchement cursor-based). |
| `qrcode` | QR Code | Rendu et téléchargement des QR codes côté client. |

### Dépendances extérieures :

| Bibliothèque | Catégorie | Justification |
|---|---|---|
| `ultralytics` | Détection | YOLOv8 pour la détection et le crop des pierres dans l'image. |
| `transformers` (Hugging Face) | Classification | ViT fine-tunable pour la classification du type de minéral. |
| `openai/clip` | Embeddings | CLIP ViT-B/32 pour l'extraction d'embeddings 512D normalisés. |
| `celery` + `redis` | Workers | Workers asynchrones pour le traitement des images en file d'attente. |
| `pillow` | Image | Prétraitement des images (resize, normalisation) avant inférence. |
| ` docker ` | Conteneurisation | Isolation du service IA, gestion des dépendances Python, déploiement simplifié. |
| ` mailer ` | Emailing | Envoi fiable des emails transactionnels (confirmation, reset, notifications). |
---

## 5. Sécurité et Performance

### Sécurité

| Risque | Mesure de mitigation |
|---|---|
| **Brute force / énumération** | Rate limiting Redis (max 5 tentatives/10min par IP sur `/auth/login`). Messages d'erreur génériques sur login et reset password. |
| **Vol de JWT** | JWT à courte durée de vie (15 min). Rotation systématique des refresh tokens. Blocklist Redis pour révocation immédiate. |
| **XSS** | Refresh token en cookie `httpOnly`. Échappement des sorties côté frontend (Angular escaping natif). Politique CSP stricte dans les headers HTTP. |
| **CSRF** | `SameSite=Strict` sur le cookie du refresh token. Double submit cookie sur les formulaires critiques. |
| **Injection SQL** | Utilisation exclusive de Doctrine ORM avec requêtes préparées. Aucune concaténation SQL dynamique. |
| **Upload malveillant** | Vérification du type MIME par lecture des magic bytes (non basée sur l'extension). Stockage sur CDN externe, jamais sur le serveur applicatif. Scan antivirus si budget le permet. |
| **SSRF** | Whitelist des domaines autorisés pour les appels HTTP sortants. Blocage des plages IP privées (10.0.0.0/8, 172.16.0.0/12, 127.0.0.0/8). |
| **Élévation de privilèges** | RBAC vérifié côté serveur à chaque requête via le middleware Symfony. Aucune logique d'autorisation côté client. |
| **Exposition de données** | Les DTOs (Data Transfer Objects) exposent uniquement les champs nécessaires. Le hash du mot de passe n'est jamais sérialisé dans les réponses API. |

### Performance

| Objectif | Mesure |
|---|---|
| Temps de réponse feed (p99) | < 200 ms en cache chaud |
| Temps de réponse analyse IA | < 5 secondes (traitement asynchrone, non bloquant) |
| Score Lighthouse | > 90 sur Performance, Accessibility, Best Practices |
| Cache Redis (feed global) | TTL 5 min, invalidation sur publication |
| Cache Redis (similarité) | TTL 1 h pour les posts > 10 vues |
| Pagination | Cursor-based sur toutes les listes volumineuses |
| Upload images | Compression et redimensionnement côté CDN avant stockage |

---

## 6. Architecture API — Endpoints



### Authentification

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `POST` | `/auth/register` | Public | Inscription. Validation email + Argon2id hashing. Email de confirmation envoyé de manière asynchrone. |
| `POST` | `/auth/login` | Public | Connexion. Retourne JWT (RS256, 15 min) + refresh token cookie `httpOnly`. |
| `POST` | `/auth/logout` | Auth | Déconnexion. Révocation du refresh token + ajout du JWT à la blocklist Redis. |
| `POST` | `/auth/refresh` | Public | Renouvellement du JWT via refresh token. Rotation du refresh token à chaque appel. |
| `POST` | `/auth/password/reset-request` | Public | Demande de reset. Réponse toujours `200 OK` pour éviter l'énumération. |
| `POST` | `/auth/password/reset` | Public | Validation du token de reset et mise à jour du mot de passe (Argon2id). |

### Profils Utilisateurs

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `GET` | `/users/me` | Auth | Profil complet de l'utilisateur connecté (stats, badges, Trust Score). |
| `PUT` | `/users/me` | Auth | Mise à jour du profil (pseudo, bio, avatar via multipart). |
| `GET` | `/users/{id}` | Public | Profil public d'un utilisateur. |
| `GET` | `/users/me/notifications` | Auth | Historique paginé des notifications. |
| `PATCH` | `/users/me/notifications/read-all` | Auth | Marquer toutes les notifications comme lues. |
| `GET` | `/users/me/points` | Auth | Total de points et historique des transactions. |

### Posts & Feed

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `GET` | `/posts` | Public | Feed global cursor-based (20/page). Filtres : `tag`, `stone_type`, `confidence_min`. Cache Redis 5 min. |
| `GET` | `/posts/feed` | Auth | Feed personnalisé. Cache Redis par utilisateur, TTL 2 min. |
| `GET` | `/posts/{id}` | Public | Détails d'un post (médias, résultat IA, commentaires, validations, posts similaires). |
| `POST` | `/posts` | Auth | Création d'un post. Upload multipart. Déclenche l'analyse IA asynchrone via Symfony Messenger. |
| `DELETE` | `/posts/{id}` | Auth | Suppression (auteur, modérateur ou admin). Soft delete + suppression CDN. |
| `POST` | `/posts/{id}/like` | Auth | Toggle like. Contrainte unique `(user_id, post_id)` en base. |
| `POST` | `/posts/{id}/comments` | Auth | Ajouter un commentaire. Notifie l'auteur du post. |
| `DELETE` | `/comments/{id}` | Auth | Supprimer un commentaire (auteur, modérateur ou admin). Soft delete. |
| `POST` | `/posts/{id}/validate` | Auth | Soumettre une validation IA. Body : `{ action, proposed_label? }`. |
| `GET` | `/posts/{id}/similar` | Public | 5 posts similaires par distance cosinus (pgvector). Cache Redis TTL 1h. |

### Collections (Vitrines)

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `POST` | `/vitrines` | Auth | Créer une Vitrine. Génère automatiquement slug et QR code PNG. |
| `GET` | `/vitrines/{slug}` | Public | Page publique d'une Vitrine. Incrémente le compteur de vues (Redis buffer). |
| `PUT` | `/vitrines/{id}` | Auth | Mettre à jour le titre ou la description. Réservé à l'auteur. |
| `POST` | `/vitrines/{id}/items` | Auth | Ajouter un post à la Vitrine (body : `{ post_id, position? }`). |
| `PUT` | `/vitrines/{id}/items/reorder` | Auth | Réordonner les items (body : `{ ordered_post_ids: [] }`). |
| `GET` | `/vitrines/{id}/qrcode` | Auth | Télécharger le QR code PNG de la Vitrine. |

### Gamification

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `GET` | `/leaderboard` | Public | Top 50 par points (Redis Sorted Set `ZREVRANGE`). |
| `GET` | `/leaderboard/me` | Auth | Position de l'utilisateur connecté dans le classement (`ZREVRANK`). |
| `GET` | `/badges` | Public | Liste de tous les badges disponibles avec conditions d'attribution. |

### Modération & Administration

| Méthode | Endpoint | Accès | Description |
|---|---|---|---|
| `POST` | `/reports` | Auth | Soumettre un signalement (`post_id`, `reason_type`, `description?`). |
| `GET` | `/admin/reports` | Moderator | Liste des signalements en attente, triés par nombre décroissant. |
| `PATCH` | `/admin/reports/{id}/resolve` | Moderator | Résoudre un signalement (`accept` ou `reject`). Notifie l'auteur. Tracé en audit log. |
| `GET` | `/admin/users` | Admin | Liste paginée des utilisateurs avec filtres (rôle, statut, Trust Score). |
| `PATCH` | `/admin/users/{id}/role` | Admin | Modifier le rôle d'un utilisateur. |
| `PATCH` | `/admin/users/{id}/ban` | Admin | Bannir ou débannir un utilisateur. Révoque tous ses refresh tokens. |
| `GET` | `/admin/stats` | Admin | KPIs de la plateforme (posts, IA, utilisateurs actifs, répartition des minéraux). |
| `POST` | `/admin/ai/fine-tune` | Admin | Déclencher un cycle de fine-tuning asynchrone (`min_trust_score`, `model_version_name`). |
| `GET` | `/admin/ai/fine-tune/{job_id}` | Admin | Consulter l'avancement d'un job de fine-tuning. |
| `GET` | `/admin/ai/models` | Admin | Liste des versions du modèle ViT avec métriques (accuracy, F1-score). |
| `PATCH` | `/admin/ai/models/{version}/activate` | Admin | Activer une version du modèle (rollback). |

---

## 7. Annexes

### Matrice des privilèges par rôle

| Fonctionnalité | Visiteur | User | Expert | Moderator | Admin |
|---|:---:|:---:|:---:|:---:|:---:|
| Consulter le feed et les posts | ✓ | ✓ | ✓ | ✓ | ✓ |
| Consulter les Vitrines publiques | ✓ | ✓ | ✓ | ✓ | ✓ |
| Créer un post | — | ✓ | ✓ | ✓ | ✓ |
| Liker / Commenter | — | ✓ | ✓ | ✓ | ✓ |
| Valider une identification | — | ✓ (pondéré) | ✓ | ✓ | ✓ |
| Créer une Vitrine | — | ✓ | ✓ | ✓ | ✓ |
| Signaler un contenu | — | ✓ | ✓ | ✓ | ✓ |
| Contribution au fine-tuning | — | Selon Trust Score | ✓ | ✓ | ✓ |
| Traiter les signalements | — | — | — | ✓ | ✓ |
| Supprimer tout contenu | — | — | — | ✓ | ✓ |
| Gérer les rôles utilisateurs | — | — | — | — | ✓ |
| Bannir un utilisateur | — | — | — | — | ✓ |
| Déclencher le fine-tuning IA | — | — | — | — | ✓ |
| Gérer le catalogue Stone | — | — | — | — | ✓ |
| Consulter les KPIs & statistiques | — | — | — | — | ✓ |
| Activer / rollback un modèle IA | — | — | — | — | ✓ |

### Contraintes non-fonctionnelles

| Contrainte | Valeur cible |
|---|---|
| Disponibilité | 99.5% (hors maintenance planifiée) |
| Temps de réponse API (p99, cache chaud) | < 200 ms |
| Temps de traitement IA asynchrone | < 5 secondes |
| Score Lighthouse Performance | > 90 |
| Score Lighthouse Accessibility | > 90 (WCAG 2.1 AA) |
| Taille maximale upload image | 10 Mo |
| Taille maximale upload vidéo | 100 Mo |
| Durée maximale vidéo | 60 secondes |
| Rétention des audit logs | 12 mois |
| Fréquence de fine-tuning IA | Configurable (recommandé : mensuel) |

### Évolutions possibles (hors scope V2)

- Application mobile native iOS / Android (React Native ou Angular + Capacitor).
- Scan IA en temps réel via la caméra du terminal (mode AR avec TensorFlow.js ou Core ML).
- Marketplace d'achat et de vente de pierres entre membres avec système d'évaluation.
- Intégration avec Mindat.org (API de la base de données minéralogique mondiale) pour enrichir le catalogue Stone.
- Social graph avancé : recommandations de profils, groupes thématiques, abonnements.
- Certification Expert avec épreuve de validation supervisée.
- Export des données utilisateur (RGPD, format JSON) via un endpoint dédié.
- amélioration continue du pipeline IA avec intégration de modèles de segmentation d'image pour isoler précisément les pierres du fond, améliorant ainsi la qualité des identifications et des embeddings.
- Mise en place d'un système de feedback utilisateur sur les résultats IA (ex : "L'identification est-elle correcte ?") pour collecter des données supplémentaires sur la précision perçue et guider les cycles de fine-tuning.
- Développement d'une API publique pour permettre à des applications tierces d'accéder aux données de GemLink (posts, Vitrines, profils) de manière sécurisée via des clés d'API et des quotas d'utilisation.