# Vision métier globale

Ton application n’est pas seulement un “réseau social de pierres”.

C’est une plateforme communautaire de reconnaissance, validation et valorisation minéralogique assistée par IA.

Cela change la manière de concevoir le domaine métier.

## Domaine métier principal

Le système peut être découpé en 6 sous-domaines métier.

### 1. Identity & Access

**Responsabilité**
- gérer les utilisateurs
- gérer l’authentification
- gérer les permissions
- gérer les rôles
- garantir la sécurité

**Entités principales**
- `User`
- `Role`
- `Session`
- `RefreshToken`

**Règles métier**
- un email doit être unique
- un utilisateur peut être banni
- certains rôles nécessitent une validation admin

**Pourquoi le séparer ?**
Parce que l’authentification ne doit pas polluer :
- les posts
- les validations IA
- les scores

### 2. Social Domain

Le cœur du réseau social.

**Responsabilité**
- gérer les posts
- gérer les commentaires
- gérer les likes
- gérer le feed
- gérer les médias
- gérer les tags

**Entités principales**
- `Post`
- `Comment`
- `Like`
- `Media`
- `Tag`

**Value Objects possibles**
- `PostContent`
- `MediaType`
- `Visibility`

**Événements métier**
- `PostCreated`
- `CommentAdded`
- `PostLiked`
- `PostDeleted`

### 3. Mineral Intelligence Domain

Le domaine IA / scientifique. C’est probablement le plus différenciant.

**Responsabilité**
- gérer la classification IA
- gérer les embeddings
- gérer la similarité
- gérer la validation scientifique
- gérer les clusters

**Entités principales**
- `AIAnalysis`
- `Embedding`
- `MineralPrediction`
- `Cluster`
- `Validation`

**Value Objects**
- `ConfidenceScore`
- `VectorEmbedding`
- `PredictionResult`

**Événements**
- `ImageAnalyzed`
- `EmbeddingGenerated`
- `PredictionValidated`
- `ClusterUpdated`

### 4. Reputation Domain

Très important. C’est ici que le système devient “communautaire intelligent”.

**Responsabilité**
- gérer le Trust Score
- gérer les points
- gérer les badges
- gérer les niveaux
- gérer l’expertise

**Entités principales**
- `TrustProfile`
- `Badge`
- `Level`
- `ReputationHistory`

**Règles métier**
- si validation correcte : + trust
- si validation incorrecte : - trust

**Événements**
- `TrustScoreUpdated`
- `BadgeUnlocked`
- `LevelUp`

### 5. Collection Domain

Le domaine “Setups”.

**Responsabilité**
- gérer les collections
- gérer les vitrines publiques
- gérer les QR codes
- gérer l’organisation visuelle

**Entités principales**
- `Setup`
- `SetupItem`
- `QRCode`

**Événements**
- `SetupCreated`
- `StoneAddedToSetup`
- `SetupShared`

### 6. Moderation Domain

Très important pour un réseau social.

**Responsabilité**
- gérer les signalements
- gérer les bannissements
- gérer l’audit
- gérer les décisions de modération

**Entités principales**
- `Report`
- `ModerationAction`
- `Ban`
- `AuditLog`

**Événements**
- `ContentReported`
- `UserBanned`
- `ReportResolved`

## Architecture mentale du domaine

On peut voir le système ainsi :

```
Identity Domain
      |
      v
Social Domain <--> Reputation Domain
      |
      v
Collection Domain
      |
      v
Mineral Intelligence Domain
      |
      v
Moderation Domain
```

## Ce que ça change concrètement

Quand tu coderas en Symfony, tu ne penses plus seulement en termes de :

- Controller
- Service
- Repository

Tu commences à penser :

- Domain
- règles métier
- événements

Cela améliore :

- la qualité du code
- la maintenabilité
- la scalabilité

## Exemple concret

Au lieu de :

```php
$post->like();
```

Tu pourrais avoir :

```php
$likeService->likePost($user, $post);
```

Ce service déclenche alors :

- `PostLiked`
- mise à jour du cache Redis
- mise à jour de la réputation
- notification
- analytics

Le tout sans couplage direct.

## Ce que je te recommande maintenant

Définis clairement :

1. Les agrégats métiers
- `Post Aggregate`
- `User Aggregate`
- `Trust Aggregate`

2. Les événements métier
- `PostPublished`
- `AIValidationAccepted`
- `TrustScoreIncreased`

3. Les invariants métier
- un user ne peut liker qu’une fois
- un Trust Score doit rester dans [0, 100]
- un post supprimé supprime ses médias CDN
