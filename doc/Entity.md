# Entities Reference

Ce document fournit une référence rapide aux entités principales de l'application, avec leurs champs clés, types de données, relations et contraintes. 


---

## Vendeur (vendeur)
- PK: `id` (uuid)
- Columns:
  - `user_id` : relation to `User` (ManyToOne, not null)
  - `companyName` : string(150)
  - `siret` : string(14)
  - `address` : text, nullable
  - `subscriptionPlan` : string(50), nullable
  - `subscription_expires_at` : datetimetz_immutable, nullable
  - `created_at` : datetimetz_immutable

## Tag (tag)
- PK: `id` (uuid)
- Columns:
  - `name` : string(50)
  - `scope` : string(20) default `GLOBAL`
  - `created_at` : datetimetz_immutable

## User (utilisateur)
- PK: `id` (uuid)
- Table constraints: unique `email`, unique `username`; indexes on `role`, `status`
- Columns (high level):
  - `username` : string(30)
  - `email` : string(255)
  - `password_hash` : string(255)
  - `avatar_url` : text, nullable
  - `bio` : string(500), nullable
  - `trust_score` : smallint, default 0
  - `role` : string(20), default `user` (allowed: user, expert, moderator, vendeur, admin)
  - `points` : integer, default 0
  - `level` : smallint, default 1
  - `status` : string(25), default `PENDING_VALIDATION`
  - `created_at` : datetime

## Publication (publication)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (onDelete CASCADE)
- Columns:
  - `title` : string(200), nullable
  - `description` : text, nullable
  - `media_url` : text
  - `media_type` : string(20) default `IMAGE`
  - `status` : string(40) default `PENDING_ANALYSIS`
  - `is_sponsored` : boolean
  - `view_count` : integer default 0
  - `created_at`, `updated_at`, `deleted_at` : datetimetz_immutable (nullable where appropriate)

## Validation (validation)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
  - `publication_id` : ManyToOne -> `Publication` (CASCADE)
  - `pierre_id` : ManyToOne -> `Pierre` (RESTRICT)
- Columns:
  - `action` : string(20) default `CONFIRM`
  - `proposedLabel` : string(100), nullable
  - `trust_score_snapshot` : smallint
  - `created_at` : datetimetz_immutable

## Badge (badge)
- PK: `id` (uuid)
- Columns:
  - `name` : string(100)
  - `description` : text, nullable
  - `condition_type` : string(50)
  - `condition_value` : integer
  - `created_at` : datetimetz_immutable

## VersionModeleIa / AiModelVersion (ai_model_version)
- PK: `id` (uuid)
- Columns:
  - `name` : string(50)
  - `model_type` : string(20)
  - `accuracy` : decimal(5,4), nullable
  - `description` : text, nullable
  - `status` : string(20) default `TRAINING`
  - `created_at` : datetimetz_immutable

## Notification (notification)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
- Columns:
  - `type` : string(50)
  - `target_id` : uuid
  - `target_type` : string(50)
  - `is_read` : boolean
  - `created_at` : datetimetz_immutable

## JobFineTuning (job_fine_tuning)
- PK: `id` (uuid)
- Relations:
  - `version_modele_ia_id` : ManyToOne -> `VersionModeleIa` (RESTRICT)
- Columns:
  - `min_trust_score` : smallint
  - `status` : string(20) default `PENDING`
  - `created_at`, `started_at`, `completed_at` : datetimetz_immutable (nullable where appropriate)

## Commentaire (commentaire)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
  - `publication_id` : ManyToOne -> `Publication` (CASCADE)
- Columns:
  - `content` : text
  - `created_at`, `updated_at`, `deleted_at` : datetimetz_immutable (nullable where appropriate)

## Pierre (pierre)
- PK: `id` (uuid)
- Columns:
  - `name` : string(100)
  - `category` : string(100), nullable
  - `hardness` : decimal(4,2), nullable
  - `description` : text, nullable
  - `created_at` : datetimetz_immutable

## Vitrine (vitrine)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
- Columns:
  - `title` : string(100)
  - `slug` : string(150)
  - `description` : text, nullable
  - `view_count` : integer default 0
  - `created_at`, `updated_at` : datetimetz_immutable

## Embedding (embedding)
- PK: `id` (uuid)
- Relations:
  - `publication_id` : ManyToOne -> `Publication` (RESTRICT)
  - `version_modele_ia_id` : ManyToOne -> `VersionModeleIa` (RESTRICT)
- Columns:
  - `vector_data` : vector (pgvector). Stored in DB as `vector(512)` (see migrations). Mapped in entities with Doctrine type `vector` (no length attribute to avoid diffs).
  - `created_at` : datetimetz_immutable

## RefreshToken (refresh_token)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
- Columns:
  - `token_hash` : string(64)
  - `expires_at` : datetimetz_immutable
  - `revoked_at` : datetimetz_immutable, nullable
  - `created_at` : datetimetz_immutable

## Groupe (groupe)
- PK: `id` (uuid)
- Columns:
  - `name` : string(100)
  - `description` : text, nullable
  - `visibility` : string(20) default `PUBLIC`
  - `created_by` : ManyToOne -> `User` (createdBy, CASCADE)
  - `created_at` : datetimetz_immutable

## AuditLog (audit_log)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (RESTRICT)
- Columns:
  - `action` : string(50)
  - `target_type` : string(50)
  - `target_id` : uuid
  - `created_at` : datetimetz_immutable

## Report (report)
- PK: `id` (uuid)
- Relations:
  - `user_id` : ManyToOne -> `User` (CASCADE)
  - `publication_id` : ManyToOne -> `Publication` (CASCADE)
- Columns:
  - `reason_type` : string(50) default `SPAM`
  - `description` : text, nullable
  - `status` : string(20) default `PENDING`
  - `created_at` : datetimetz_immutable

---



