# MPD — Modèle Physique des Données — GemLink

> **SGBD cible :** PostgreSQL 16+  
> **Extensions requises :** `uuid-ossp`, `pgvector`  
> **Encodage :** UTF-8
---

## Table des matières

- [MPD — Modèle Physique des Données — GemLink](#mpd--modèle-physique-des-données--gemlink)
  - [Table des matières](#table-des-matières)
    - [3.2 VENDEUR](#32-vendeur)
    - [3.3 REFRESH\_TOKEN](#33-refresh_token)
    - [3.4 STONE](#34-stone)
    - [3.5 TAG](#35-tag)
    - [3.6 POST](#36-post)
    - [3.7 POST\_TAG](#37-post_tag)
    - [3.8 COMMENT](#38-comment)
    - [3.9 LIKE](#39-like)
    - [3.10 EMBEDDING](#310-embedding)
    - [3.11 AI\_MODEL\_VERSION](#311-ai_model_version)
    - [3.12 FINE\_TUNE\_JOB](#312-fine_tune_job)
    - [3.13 VALIDATION](#313-validation)
    - [3.14 VITRINE](#314-vitrine)
    - [3.15 VITRINE\_ITEM](#315-vitrine_item)
    - [3.16 BADGE](#316-badge)
    - [3.17 USER\_BADGE](#317-user_badge)
    - [3.18 NOTIFICATION](#318-notification)
    - [3.19 POINT\_TRANSACTION](#319-point_transaction)
    - [3.20 REPORT](#320-report)
    - [3.21 GROUP\_ENTITY](#321-group_entity)
    - [3.22 GROUP\_MEMBER](#322-group_member)
    - [3.23 FOLLOW](#323-follow)
    - [3.24 INVOICE](#324-invoice)
    - [3.25 AUDIT\_LOG](#325-audit_log)
  - [4. Index complémentaires](#4-index-complémentaires)
  - [5. Contraintes et triggers](#5-contraintes-et-triggers)
    - [Trigger : mise à jour automatique de `updated_at`](#trigger--mise-à-jour-automatique-de-updated_at)
    - [Trigger : masquage automatique d'un post à 5 signalements](#trigger--masquage-automatique-dun-post-à-5-signalements)
    - [Trigger : protection de l'immuabilité de `audit_log`](#trigger--protection-de-limmuabilité-de-audit_log)
    - [Trigger : recalcul du Trust Score après validation](#trigger--recalcul-du-trust-score-après-validation)
  - [6. Politique de sécurité (Row Level Security)](#6-politique-de-sécurité-row-level-security)
  - [7. Résumé des relations](#7-résumé-des-relations)

```sql


-- Statut du compte utilisateur
CREATE TYPE user_status AS ENUM (
    'PENDING_VALIDATION',
    'ACTIVE',
    'BANNED'
);

-- Rôle RBAC de l'utilisateur (valeurs en MAJUSCULES dans la migration)
CREATE TYPE user_role AS ENUM (
    'USER',
    'EXPERT',
    'MODERATOR',
    'VENDEUR',
    'ADMIN'
);

-- Statut d'un post
CREATE TYPE post_status AS ENUM (
    'PENDING_ANALYSIS',
    'ANALYZED',
    'ANALYSIS_FAILED',
    'AUTO_HIDDEN',
    'PUBLISHED'
);

-- Type de média d'un post
CREATE TYPE media_type AS ENUM (
    'IMAGE',
    'VIDEO'
);

-- Action de validation IA
CREATE TYPE validation_action AS ENUM (
    'CONFIRM',
    'CORRECT',
    'REJECT'
);

-- Motif de signalement
CREATE TYPE report_reason AS ENUM (
    'INAPPROPRIATE_CONTENT',
    'WRONG_IDENTIFICATION',
    'SPAM',
    'HARASSMENT'
);

-- Statut d'un signalement
CREATE TYPE report_status AS ENUM (
    'PENDING',
    'ACCEPTED',
    'REJECTED'
);

-- Type de condition d'un badge
CREATE TYPE badge_condition_type AS ENUM (
    'POST_COUNT',
    'VALIDATION_COUNT',
    'TRUST_SCORE_THRESHOLD',
    'LEVEL_REACHED'
);

-- Type d'action générant des points
CREATE TYPE point_action_type AS ENUM (
    'POST_PUBLISHED',
    'LIKE_RECEIVED',
    'VALIDATION_SUBMITTED',
    'VALIDATION_CONFIRMED'
);

-- Type de modèle IA
CREATE TYPE ai_model_type AS ENUM (
    'YOLO',
    'VIT',
    'CLIP'
);

-- Statut d'une version de modèle IA
CREATE TYPE ai_model_status AS ENUM (
    'TRAINING',
    'ACTIVE',
    'DEPRECATED'
);

-- Statut d'un job de fine-tuning
CREATE TYPE fine_tune_status AS ENUM (
    'PENDING',
    'RUNNING',
    'COMPLETED',
    'FAILED'
);

-- Visibilité d'un groupe (valeurs en MAJ dans la migration)
CREATE TYPE group_visibility AS ENUM (
    'PUBLIC',
    'PRIVATE'
);

-- Rôle dans un groupe
CREATE TYPE group_member_role AS ENUM (
    'OWNER',
    'ADMIN',
    'MEMBER'
);

-- Statut d'une facture
CREATE TYPE invoice_status AS ENUM (
    'PENDING',
    'PAID',
    'CANCELLED'
);

-- Portée d'un tag (alignée sur la migration)
CREATE TYPE tag_scope AS ENUM (
    'GLOBAL',
    'VENDEUR',
    'USER'
);
```

-- Remarque: les valeurs utilitaires (user_role, media_type, group_visibility, tag_scope, etc.) sont intentionallement en MAJUSCULES
-- pour correspondre à la migration PHP générée et appliquée dans `backend/migrations/Version20260609113454.php`.
    'admin',
    'member'
);

-- Statut d'une facture
CREATE TYPE invoice_status AS ENUM (
    'PENDING',
    'PAID',
    'CANCELLED'
);

-- Portée d'un tag
CREATE TYPE tag_scope AS ENUM (
    'global',
    'client',
    'user'
);
```

---

## 3. Tables

### 3.1 USER

```sql
CREATE TABLE "user" (
    id              UUID            PRIMARY KEY DEFAULT uuid_generate_v4(),
    username        VARCHAR(30)     NOT NULL,
    email           VARCHAR(255)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    avatar_url      TEXT,
    bio             VARCHAR(500),
    trust_score     SMALLINT        NOT NULL DEFAULT 0
                                    CONSTRAINT chk_trust_score CHECK (trust_score BETWEEN 0 AND 100),
    role            user_role       NOT NULL DEFAULT 'user',
    points          INTEGER         NOT NULL DEFAULT 0
                                    CONSTRAINT chk_points_positive CHECK (points >= 0),
    level           SMALLINT        NOT NULL DEFAULT 1
                                    CONSTRAINT chk_level_positive CHECK (level >= 1),
    status          user_status     NOT NULL DEFAULT 'PENDING_VALIDATION',
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_user_email    UNIQUE (email),
    CONSTRAINT uq_user_username UNIQUE (username)
);

CREATE INDEX idx_user_email    ON "user" (email);
CREATE INDEX idx_user_role     ON "user" (role);
CREATE INDEX idx_user_status   ON "user" (status);
CREATE INDEX idx_user_points   ON "user" (points DESC);

COMMENT ON TABLE  "user"             IS 'Comptes utilisateurs de la plateforme GemLink.';
COMMENT ON COLUMN "user".trust_score IS 'Score de fiabilité (0–100), calculé automatiquement à partir des validations IA confirmées.';
COMMENT ON COLUMN "user".role        IS 'Rôle RBAC : user, expert, moderator, client, admin.';
COMMENT ON COLUMN "user".status      IS 'Cycle de vie du compte : PENDING_VALIDATION → ACTIVE → BANNED.';
```

---

### 3.2 VENDEUR

```sql
CREATE TABLE vendeur (
    id                      UUID            PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id                 UUID            NOT NULL,
    company_name            VARCHAR(150)    NOT NULL,
    siret                   VARCHAR(14),
    address                 TEXT,
    subscription_plan       VARCHAR(50),
    subscription_expires_at TIMESTAMPTZ,
    created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_vendeur_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT uq_vendeur_user UNIQUE (user_id)
);

CREATE INDEX idx_vendeur_user_id ON vendeur (user_id);

COMMENT ON TABLE  vendeur                      IS 'Profil étendu pour les utilisateurs ayant le rôle vendeur (professionnels).';
COMMENT ON COLUMN vendeur.siret                IS 'Numéro SIRET (14 chiffres), optionnel pour les auto-entrepreneurs.';
COMMENT ON COLUMN vendeur.subscription_plan    IS 'Plan d''abonnement actif (ex : starter, pro, enterprise).';
```

---

### 3.3 REFRESH_TOKEN

```sql
CREATE TABLE refresh_token (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID        NOT NULL,
    token_hash  VARCHAR(64) NOT NULL,
    expires_at  TIMESTAMPTZ NOT NULL,
    revoked_at  TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_refresh_token_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT uq_refresh_token_hash UNIQUE (token_hash)
);

CREATE INDEX idx_refresh_token_hash    ON refresh_token (token_hash);
CREATE INDEX idx_refresh_token_user_id ON refresh_token (user_id);
CREATE INDEX idx_refresh_token_active  ON refresh_token (user_id)
    WHERE revoked_at IS NULL AND expires_at > NOW();

COMMENT ON TABLE  refresh_token            IS 'Tokens de renouvellement JWT. Stockés hashés (SHA-256), rotation à chaque renouvellement.';
COMMENT ON COLUMN refresh_token.token_hash IS 'SHA-256 du token opaque. Jamais le token brut.';
COMMENT ON COLUMN refresh_token.revoked_at IS 'NULL = token actif. Renseigné lors de la déconnexion ou du ban.';
```

---

### 3.4 STONE

```sql
CREATE TABLE stone (
    id             UUID            PRIMARY KEY DEFAULT uuid_generate_v4(),
    name           VARCHAR(100)    NOT NULL,
    category       VARCHAR(100),
    hardness       NUMERIC(4, 2)
                   CONSTRAINT chk_hardness CHECK (hardness BETWEEN 0 AND 10),
    crystal_system VARCHAR(50),
    composition    TEXT,
    description    TEXT,
    created_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_stone_name UNIQUE (name)
);

CREATE INDEX idx_stone_name     ON stone (name);
CREATE INDEX idx_stone_category ON stone (category);

COMMENT ON TABLE  stone          IS 'Catalogue officiel des minéraux. Référence taxonomique pour les identifications IA.';
COMMENT ON COLUMN stone.hardness IS 'Dureté sur l''échelle de Mohs (1 = talc, 10 = diamant).';
```

---

### 3.5 TAG

```sql
CREATE TABLE tag (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    owner_id    UUID,
    name        VARCHAR(50) NOT NULL,
    scope       tag_scope   NOT NULL DEFAULT 'global',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_tag_owner
        FOREIGN KEY (owner_id) REFERENCES "user"(id)
        ON DELETE SET NULL,
    CONSTRAINT uq_tag_name_scope_owner UNIQUE (name, scope, owner_id)
);

CREATE INDEX idx_tag_name     ON tag (name);
CREATE INDEX idx_tag_owner_id ON tag (owner_id);
CREATE INDEX idx_tag_scope    ON tag (scope);

COMMENT ON TABLE  tag        IS 'Étiquettes thématiques associées aux posts. Portée : global (plateforme), client (espace client), user (personnel).';
COMMENT ON COLUMN tag.scope  IS 'global = visible par tous ; client = créé par un Client ; user = créé par un User.';
```

---

### 3.6 POST

```sql
CREATE TABLE post (
    id           UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id      UUID        NOT NULL,
    stone_id     UUID,
    title        VARCHAR(200),
    description  TEXT,
    media_url    TEXT        NOT NULL,
    media_type   media_type  NOT NULL,
    status       post_status NOT NULL DEFAULT 'PENDING_ANALYSIS',
    view_count   INTEGER     NOT NULL DEFAULT 0
                                    CONSTRAINT chk_post_view_count CHECK (view_count >= 0),
    is_sponsored BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ,

    CONSTRAINT fk_post_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_post_stone
        FOREIGN KEY (stone_id) REFERENCES stone(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_post_user_id    ON post (user_id);
CREATE INDEX idx_post_status     ON post (status);
CREATE INDEX idx_post_stone_id   ON post (stone_id) WHERE stone_id IS NOT NULL;
CREATE INDEX idx_post_created_at ON post (created_at DESC);
CREATE INDEX idx_post_active     ON post (created_at DESC)
    WHERE deleted_at IS NULL AND status = 'PUBLISHED';
CREATE INDEX idx_post_sponsored  ON post (is_sponsored) WHERE is_sponsored = TRUE;
CREATE INDEX idx_post_view_count  ON post (view_count DESC);

COMMENT ON TABLE  post             IS 'Publications des utilisateurs contenant un média (image ou vidéo) d''une pierre.';
COMMENT ON COLUMN post.status      IS 'Cycle de vie : PENDING_ANALYSIS → ANALYZED / ANALYSIS_FAILED → PUBLISHED / AUTO_HIDDEN.';
COMMENT ON COLUMN post.deleted_at  IS 'Soft delete : valeur NULL = post actif. Non NULL = supprimé logiquement.';
COMMENT ON COLUMN post.is_sponsored IS 'TRUE si le post est un contenu sponsorisé validé par l''Admin.';
```

---

### 3.7 POST_TAG

```sql
CREATE TABLE post_tag (
    post_id UUID NOT NULL,
    tag_id  UUID NOT NULL,

    CONSTRAINT pk_post_tag PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_post_tag_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_post_tag_tag
        FOREIGN KEY (tag_id) REFERENCES tag(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_post_tag_tag_id ON post_tag (tag_id);

COMMENT ON TABLE post_tag IS 'Table de jointure many-to-many entre post et tag.';
```

---

### 3.8 COMMENT

```sql
CREATE TABLE comment (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id     UUID        NOT NULL,
    user_id     UUID        NOT NULL,
    content     VARCHAR(1000) NOT NULL
                CONSTRAINT chk_comment_content CHECK (LENGTH(TRIM(content)) > 0),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ,

    CONSTRAINT fk_comment_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comment_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_comment_post_id    ON comment (post_id);
CREATE INDEX idx_comment_user_id    ON comment (user_id);
CREATE INDEX idx_comment_created_at ON comment (post_id, created_at ASC)
    WHERE deleted_at IS NULL;

COMMENT ON TABLE  comment           IS 'Commentaires textuels sous les posts. Soft delete via deleted_at.';
COMMENT ON COLUMN comment.deleted_at IS 'Soft delete : conservé en base pour l''audit log.';
```

---

### 3.9 LIKE

```sql
CREATE TABLE "like" (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id     UUID        NOT NULL,
    user_id     UUID        NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_like_post_user UNIQUE (post_id, user_id),
    CONSTRAINT fk_like_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_like_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_like_post_id ON "like" (post_id);
CREATE INDEX idx_like_user_id ON "like" (user_id);

COMMENT ON TABLE "like" IS 'Likes des utilisateurs sur les posts. Contrainte unique (post_id, user_id) : un seul like par utilisateur par post.';
```

---

### 3.10 EMBEDDING

```sql
CREATE TABLE embedding (
    id               UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id          UUID          NOT NULL,
    model_version_id UUID          NOT NULL,
    vector_data      vector(512)   NOT NULL,
    created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_embedding_post    UNIQUE (post_id),
    CONSTRAINT fk_embedding_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_embedding_model
        FOREIGN KEY (model_version_id) REFERENCES ai_model_version(id)
        ON DELETE RESTRICT
);

-- Index IVFFlat pour la recherche de plus proches voisins (cosine similarity)
-- lists = 100 est recommandé pour < 1 million de vecteurs
CREATE INDEX idx_embedding_vector
    ON embedding USING ivfflat (vector_data vector_cosine_ops)
    WITH (lists = 100);

CREATE INDEX idx_embedding_model_version ON embedding (model_version_id);

COMMENT ON TABLE  embedding               IS 'Embeddings vectoriels float32[512] générés par CLIP pour chaque post analysé.';
COMMENT ON COLUMN embedding.vector_data   IS 'Vecteur L2-normalisé de 512 dimensions. Requêtes via l''opérateur <=> (cosine distance).';
COMMENT ON COLUMN embedding.model_version_id IS 'Traçabilité : version du modèle CLIP ayant produit cet embedding.';
```

---

### 3.11 AI_MODEL_VERSION

```sql
CREATE TABLE ai_model_version (
    id          UUID            PRIMARY KEY DEFAULT uuid_generate_v4(),
    name        VARCHAR(50)     NOT NULL,
    model_type  ai_model_type   NOT NULL,
    accuracy    NUMERIC(5, 4)
                CONSTRAINT chk_accuracy CHECK (accuracy BETWEEN 0 AND 1),
    f1_score    NUMERIC(5, 4)
                CONSTRAINT chk_f1_score CHECK (f1_score BETWEEN 0 AND 1),
    status      ai_model_status NOT NULL DEFAULT 'TRAINING',
    created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_ai_model_version_name UNIQUE (name)
);

CREATE INDEX idx_ai_model_version_status ON ai_model_version (status);
CREATE INDEX idx_ai_model_version_type   ON ai_model_version (model_type);

COMMENT ON TABLE  ai_model_version         IS 'Versions des modèles IA déployés (YOLO, ViT, CLIP). Permet le versioning et le rollback.';
COMMENT ON COLUMN ai_model_version.status  IS 'TRAINING = en cours ; ACTIVE = déployé ; DEPRECATED = remplacé.';
COMMENT ON COLUMN ai_model_version.accuracy IS 'Précision mesurée sur le dataset de validation (0–1).';
```

---

### 3.12 FINE_TUNE_JOB

```sql
CREATE TABLE fine_tune_job (
    id               UUID              PRIMARY KEY DEFAULT uuid_generate_v4(),
    model_version_id UUID              NOT NULL,
    min_trust_score  SMALLINT          NOT NULL
                     CONSTRAINT chk_min_trust CHECK (min_trust_score BETWEEN 0 AND 100),
    status           fine_tune_status  NOT NULL DEFAULT 'PENDING',
    progress         SMALLINT          NOT NULL DEFAULT 0
                     CONSTRAINT chk_progress CHECK (progress BETWEEN 0 AND 100),
    logs             TEXT,
    created_at       TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ       NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_fine_tune_job_model
        FOREIGN KEY (model_version_id) REFERENCES ai_model_version(id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_fine_tune_job_status ON fine_tune_job (status);

COMMENT ON TABLE  fine_tune_job                  IS 'Jobs de fine-tuning déclenchés par l''Admin. Suivi en temps réel via status et progress.';
COMMENT ON COLUMN fine_tune_job.min_trust_score  IS 'Seuil minimum de Trust Score pour inclure une validation dans le dataset d''entraînement.';
COMMENT ON COLUMN fine_tune_job.logs             IS 'Logs d''exécution du cycle de fine-tuning, agrégés progressivement.';
```

---

### 3.13 VALIDATION

```sql
CREATE TABLE validation (
    id                    UUID              PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id               UUID              NOT NULL,
    user_id               UUID              NOT NULL,
    action                validation_action NOT NULL,
    proposed_label        VARCHAR(100),
    trust_score_snapshot  SMALLINT          NOT NULL
                          CONSTRAINT chk_ts_snapshot CHECK (trust_score_snapshot BETWEEN 0 AND 100),
    created_at            TIMESTAMPTZ       NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_validation_post_user UNIQUE (post_id, user_id),
    CONSTRAINT fk_validation_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_validation_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_proposed_label
        CHECK (action != 'correct' OR proposed_label IS NOT NULL)
);

CREATE INDEX idx_validation_post_id ON validation (post_id);
CREATE INDEX idx_validation_user_id ON validation (user_id);
CREATE INDEX idx_validation_action  ON validation (post_id, action);

COMMENT ON TABLE  validation                      IS 'Validations communautaires des identifications IA. Une par (post, user).';
COMMENT ON COLUMN validation.trust_score_snapshot IS 'Snapshot du Trust Score au moment de la soumission. Immuable pour garantir la traçabilité.';
COMMENT ON COLUMN validation.proposed_label       IS 'Obligatoire si action = correct. Label alternatif proposé par le validateur.';
```

---

### 3.14 VITRINE

```sql
CREATE TABLE vitrine (
    id           UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id      UUID        NOT NULL,
    title        VARCHAR(100) NOT NULL
                 CONSTRAINT chk_vitrine_title CHECK (LENGTH(TRIM(title)) > 0),
    description  VARCHAR(500),
    slug         VARCHAR(150) NOT NULL,
    qr_code_url  TEXT,
    view_count   INTEGER     NOT NULL DEFAULT 0
                 CONSTRAINT chk_view_count CHECK (view_count >= 0),
    is_sponsored BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_vitrine_slug UNIQUE (slug),
    CONSTRAINT fk_vitrine_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_vitrine_slug    ON vitrine (slug);
CREATE INDEX idx_vitrine_user_id ON vitrine (user_id);

COMMENT ON TABLE  vitrine             IS 'Collections publiques de posts. Accessibles via URL canonique (slug) sans authentification.';
COMMENT ON COLUMN vitrine.slug        IS 'Identifiant URL-safe généré à partir du titre (lowercase, tirets). Unique en base.';
COMMENT ON COLUMN vitrine.view_count  IS 'Compteur bufférisé dans Redis et persisté en base toutes les 60 secondes.';
```

---

### 3.15 VITRINE_ITEM

```sql
CREATE TABLE vitrine_item (
    id          UUID    PRIMARY KEY DEFAULT uuid_generate_v4(),
    vitrine_id  UUID    NOT NULL,
    post_id     UUID    NOT NULL,
    position    INTEGER NOT NULL DEFAULT 0
                CONSTRAINT chk_position CHECK (position >= 0),

    CONSTRAINT uq_vitrine_item_pair UNIQUE (vitrine_id, post_id),
    CONSTRAINT fk_vitrine_item_vitrine
        FOREIGN KEY (vitrine_id) REFERENCES vitrine(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_vitrine_item_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_vitrine_item_vitrine_id ON vitrine_item (vitrine_id, position ASC);
CREATE INDEX idx_vitrine_item_post_id    ON vitrine_item (post_id);

COMMENT ON TABLE  vitrine_item          IS 'Items d''une Vitrine. Le champ position permet l''ordonnancement par glisser-déposer.';
COMMENT ON COLUMN vitrine_item.position IS 'Entier de tri croissant. Géré par le frontend lors du drag-and-drop.';
```

---

### 3.16 BADGE

```sql
CREATE TABLE badge (
    id              UUID                 PRIMARY KEY DEFAULT uuid_generate_v4(),
    created_by      UUID,
    name            VARCHAR(100)         NOT NULL,
    description     TEXT,
    icon_url        TEXT,
    condition_type  badge_condition_type NOT NULL,
    condition_value INTEGER              NOT NULL
                    CONSTRAINT chk_condition_value CHECK (condition_value > 0),
    is_custom       BOOLEAN              NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ          NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_badge_name      UNIQUE (name),
    CONSTRAINT fk_badge_creator
        FOREIGN KEY (created_by) REFERENCES "user"(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_badge_condition_type ON badge (condition_type);
CREATE INDEX idx_badge_is_custom      ON badge (is_custom);

COMMENT ON TABLE  badge            IS 'Récompenses attribuées automatiquement via des Event Listeners Symfony.';
COMMENT ON COLUMN badge.is_custom  IS 'TRUE si créé par un Client pour sa propre galerie.';
COMMENT ON COLUMN badge.condition_type IS 'Type de déclencheur : POST_COUNT, VALIDATION_COUNT, TRUST_SCORE_THRESHOLD, LEVEL_REACHED.';
```

---

### 3.17 USER_BADGE

```sql
CREATE TABLE user_badge (
    user_id   UUID        NOT NULL,
    badge_id  UUID        NOT NULL,
    earned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT pk_user_badge PRIMARY KEY (user_id, badge_id),
    CONSTRAINT fk_user_badge_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_badge_badge
        FOREIGN KEY (badge_id) REFERENCES badge(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_user_badge_user_id  ON user_badge (user_id);
CREATE INDEX idx_user_badge_badge_id ON user_badge (badge_id);

COMMENT ON TABLE user_badge IS 'Attribution d''un badge à un utilisateur. Contrainte PK garantit l''unicité : un badge attribué une seule fois.';
```

---

### 3.18 NOTIFICATION

```sql
CREATE TABLE notification (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID        NOT NULL,
    type        VARCHAR(50) NOT NULL,
    content     TEXT        NOT NULL,
    is_read     BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_notification_user_id ON notification (user_id, created_at DESC);
CREATE INDEX idx_notification_unread  ON notification (user_id)
    WHERE is_read = FALSE;

COMMENT ON TABLE  notification       IS 'Notifications in-app envoyées aux utilisateurs suite à des événements applicatifs.';
COMMENT ON COLUMN notification.type  IS 'Catégorie : LIKE_RECEIVED, COMMENT_RECEIVED, BADGE_EARNED, LEVEL_UP, ANALYSIS_DONE, POST_MODERATED…';
```

---

### 3.19 POINT_TRANSACTION

```sql
CREATE TABLE point_transaction (
    id          UUID             PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID             NOT NULL,
    action_type point_action_type NOT NULL,
    amount      SMALLINT         NOT NULL
                CONSTRAINT chk_amount_nonzero CHECK (amount != 0),
    created_at  TIMESTAMPTZ      NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_point_transaction_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_point_transaction_user_id ON point_transaction (user_id, created_at DESC);

COMMENT ON TABLE  point_transaction        IS 'Historique immuable de chaque attribution ou déduction de points. Source de vérité pour les totaux.';
COMMENT ON COLUMN point_transaction.amount IS 'Positif = gain, négatif = pénalité (si applicable).';
```

---

### 3.20 REPORT

```sql
CREATE TABLE report (
    id          UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id     UUID          NOT NULL,
    reporter_id UUID          NOT NULL,
    reason_type report_reason NOT NULL,
    description TEXT,
    status      report_status NOT NULL DEFAULT 'PENDING',
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_report_post_user UNIQUE (post_id, reporter_id),
    CONSTRAINT fk_report_post
        FOREIGN KEY (post_id) REFERENCES post(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_report_reporter
        FOREIGN KEY (reporter_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_report_status  ON report (status, created_at DESC);
CREATE INDEX idx_report_post_id ON report (post_id);

COMMENT ON TABLE report IS 'Signalements de contenus inappropriés. Unicité (post_id, reporter_id) : un signalement par utilisateur par post.';
```

---

### 3.21 GROUP_ENTITY

```sql
CREATE TABLE group_entity (
    id          UUID             PRIMARY KEY DEFAULT uuid_generate_v4(),
    owner_id    UUID             NOT NULL,
    name        VARCHAR(100)     NOT NULL,
    description TEXT,
    visibility  group_visibility NOT NULL DEFAULT 'public',
    created_at  TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ      NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_group_owner
        FOREIGN KEY (owner_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_group_owner_id   ON group_entity (owner_id);
CREATE INDEX idx_group_visibility ON group_entity (visibility);

COMMENT ON TABLE group_entity IS 'Groupes communautaires créés par les utilisateurs. Nommée group_entity pour éviter le conflit avec le mot-clé SQL GROUP.';
```

---

### 3.22 GROUP_MEMBER

```sql
CREATE TABLE group_member (
    group_id  UUID             NOT NULL,
    user_id   UUID             NOT NULL,
    role      group_member_role NOT NULL DEFAULT 'member',
    joined_at TIMESTAMPTZ      NOT NULL DEFAULT NOW(),

    CONSTRAINT pk_group_member PRIMARY KEY (group_id, user_id),
    CONSTRAINT fk_group_member_group
        FOREIGN KEY (group_id) REFERENCES group_entity(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_group_member_user
        FOREIGN KEY (user_id) REFERENCES "user"(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_group_member_user_id ON group_member (user_id);

COMMENT ON TABLE group_member IS 'Membres d''un groupe avec leur rôle interne (owner, admin, member).';
```

---

### 3.23 FOLLOW

```sql
CREATE TABLE follow (
    follower_id UUID        NOT NULL,
    followed_id UUID        NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT pk_follow PRIMARY KEY (follower_id, followed_id),
    CONSTRAINT fk_follow_follower
        FOREIGN KEY (follower_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_follow_followed
        FOREIGN KEY (followed_id) REFERENCES "user"(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_follow_no_self
        CHECK (follower_id <> followed_id)
);

CREATE INDEX idx_follow_follower_id ON follow (follower_id);
CREATE INDEX idx_follow_followed_id ON follow (followed_id);

COMMENT ON TABLE follow IS 'Abonnements entre utilisateurs. Contrainte empêche l''auto-abonnement.';
```

---

### 3.24 INVOICE

```sql
CREATE TABLE invoice (
    id          UUID           PRIMARY KEY DEFAULT uuid_generate_v4(),
    client_id   UUID           NOT NULL,
    amount      NUMERIC(10, 2) NOT NULL
                CONSTRAINT chk_invoice_amount CHECK (amount > 0),
    status      invoice_status NOT NULL DEFAULT 'PENDING',
    issued_at   TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    paid_at     TIMESTAMPTZ,

    CONSTRAINT fk_invoice_client
        FOREIGN KEY (client_id) REFERENCES vendeur(id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_invoice_client_id ON invoice (client_id);
CREATE INDEX idx_invoice_status    ON invoice (status);

COMMENT ON TABLE  invoice           IS 'Factures émises pour les abonnements Client. ON DELETE RESTRICT : un client avec des factures ne peut pas être supprimé.';
COMMENT ON COLUMN invoice.amount    IS 'Montant en euros (TTC). Précision à 2 décimales.';
COMMENT ON COLUMN invoice.paid_at   IS 'NULL = non payée. Renseigné lors de la confirmation de paiement (webhook Stripe).';
```

---

### 3.25 AUDIT_LOG

```sql
CREATE TABLE audit_log (
    id          UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_id    UUID        NOT NULL,
    action      VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id   UUID        NOT NULL,
    reason      TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_audit_log_actor
        FOREIGN KEY (actor_id) REFERENCES "user"(id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_audit_log_actor_id   ON audit_log (actor_id);
CREATE INDEX idx_audit_log_target     ON audit_log (target_type, target_id);
CREATE INDEX idx_audit_log_created_at ON audit_log (created_at DESC);

COMMENT ON TABLE  audit_log             IS 'Journal d''audit immuable. Aucun UPDATE ni DELETE autorisé (voir section sécurité). Rétention : 12 mois minimum.';
COMMENT ON COLUMN audit_log.action      IS 'Ex : POST_DELETED, USER_BANNED, REPORT_ACCEPTED, MODEL_ACTIVATED…';
COMMENT ON COLUMN audit_log.target_type IS 'Type de l''entité ciblée : post, comment, user, report…';
COMMENT ON COLUMN audit_log.actor_id    IS 'ON DELETE RESTRICT : un modérateur avec des actions ne peut pas être supprimé.';
```

---

## 4. Index complémentaires

```sql
-- Recherche full-text sur les posts (titre + description)
CREATE INDEX idx_post_fts ON post
    USING gin(to_tsvector('french', COALESCE(title, '') || ' ' || COALESCE(description, '')))
    WHERE deleted_at IS NULL;

-- Recherche full-text sur le catalogue Stone
CREATE INDEX idx_stone_fts ON stone
    USING gin(to_tsvector('french', name || ' ' || COALESCE(description, '')));

-- Feed global paginé (cursor-based) : posts publiés triés par date décroissante
CREATE INDEX idx_post_feed ON post (created_at DESC, id)
    WHERE deleted_at IS NULL AND status = 'PUBLISHED';

-- Leaderboard : top N utilisateurs actifs par points
CREATE INDEX idx_user_leaderboard ON "user" (points DESC)
    WHERE status = 'ACTIVE';

-- Signalements en attente par post (pour le seuil de 5 déclenchant AUTO_HIDDEN)
CREATE INDEX idx_report_pending_post ON report (post_id)
    WHERE status = 'PENDING';

-- Validations disponibles pour le fine-tuning (filtrées par Trust Score snapshot)
CREATE INDEX idx_validation_finetune ON validation (trust_score_snapshot DESC)
    WHERE action IN ('confirm', 'correct');

-- Vitrines publiques actives (pour le feed de collections)
CREATE INDEX idx_vitrine_public ON vitrine (created_at DESC)
    WHERE is_sponsored = FALSE;

-- Notifications non lues par utilisateur
CREATE INDEX idx_notification_unread_user ON notification (user_id, created_at DESC)
    WHERE is_read = FALSE;
```

---

## 5. Contraintes et triggers

### Trigger : mise à jour automatique de `updated_at`

```sql
-- Fonction générique réutilisée par tous les triggers updated_at
CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Application sur toutes les tables concernées
CREATE TRIGGER trg_user_updated_at
    BEFORE UPDATE ON "user"
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_post_updated_at
    BEFORE UPDATE ON post
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_comment_updated_at
    BEFORE UPDATE ON comment
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_vitrine_updated_at
    BEFORE UPDATE ON vitrine
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_stone_updated_at
    BEFORE UPDATE ON stone
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_report_updated_at
    BEFORE UPDATE ON report
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_fine_tune_job_updated_at
    BEFORE UPDATE ON fine_tune_job
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

CREATE TRIGGER trg_vendeur_updated_at
    BEFORE UPDATE ON vendeur
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
```

### Trigger : masquage automatique d'un post à 5 signalements

```sql
CREATE OR REPLACE FUNCTION trigger_auto_hide_post()
RETURNS TRIGGER AS $$
DECLARE
    v_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO v_count
    FROM report
    WHERE post_id = NEW.post_id AND status = 'PENDING';

    IF v_count >= 5 THEN
        UPDATE post
        SET status = 'AUTO_HIDDEN', updated_at = NOW()
        WHERE id = NEW.post_id AND status = 'PUBLISHED';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_report_auto_hide
    AFTER INSERT ON report
    FOR EACH ROW EXECUTE FUNCTION trigger_auto_hide_post();
```

### Trigger : protection de l'immuabilité de `audit_log`

```sql
CREATE OR REPLACE FUNCTION trigger_deny_audit_mutation()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'La table audit_log est immuable. UPDATE et DELETE sont interdits.';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_audit_log_no_update
    BEFORE UPDATE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION trigger_deny_audit_mutation();

CREATE TRIGGER trg_audit_log_no_delete
    BEFORE DELETE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION trigger_deny_audit_mutation();
```

### Trigger : recalcul du Trust Score après validation

```sql
CREATE OR REPLACE FUNCTION trigger_update_trust_score()
RETURNS TRIGGER AS $$
DECLARE
    v_confirmed  INTEGER;
    v_total      INTEGER;
    v_anchor     NUMERIC;
    v_new_score  INTEGER;
BEGIN
    SELECT
        COUNT(*) FILTER (WHERE action = 'confirm') AS confirmed,
        COUNT(*) AS total
    INTO v_confirmed, v_total
    FROM validation
    WHERE user_id = NEW.user_id;

    IF v_total = 0 THEN
        RETURN NEW;
    END IF;

    -- Facteur d'ancienneté : ln(total + 1), plafonné à 1
    v_anchor    := LEAST(1.0, LN(v_total + 1) / LN(50));
    v_new_score := ROUND((v_confirmed::NUMERIC / v_total) * 100 * (0.5 + 0.5 * v_anchor))::INTEGER;

    UPDATE "user"
    SET trust_score = GREATEST(0, LEAST(100, v_new_score)),
        updated_at  = NOW()
    WHERE id = NEW.user_id;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validation_trust_score
    AFTER INSERT ON validation
    FOR EACH ROW EXECUTE FUNCTION trigger_update_trust_score();
```

---

## 6. Politique de sécurité (Row Level Security)

```sql
-- Activer RLS sur les tables sensibles
ALTER TABLE "user"       ENABLE ROW LEVEL SECURITY;
ALTER TABLE post         ENABLE ROW LEVEL SECURITY;
ALTER TABLE comment      ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_log    ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice      ENABLE ROW LEVEL SECURITY;

-- Utilisateurs applicatifs
-- gemlink_app  : compte de l'API Symfony (accès lecture/écriture standard)
-- gemlink_admin: compte du dashboard Admin (accès complet)

-- Post : un user ne voit que les posts non supprimés (soft delete)
CREATE POLICY policy_post_visible
    ON post FOR SELECT
    TO gemlink_app
    USING (deleted_at IS NULL);

-- Comment : idem
CREATE POLICY policy_comment_visible
    ON comment FOR SELECT
    TO gemlink_app
    USING (deleted_at IS NULL);

-- Audit log : lecture seule pour gemlink_app, écriture uniquement INSERT
CREATE POLICY policy_audit_log_insert_only
    ON audit_log FOR INSERT
    TO gemlink_app
    WITH CHECK (TRUE);

CREATE POLICY policy_audit_log_select
    ON audit_log FOR SELECT
    TO gemlink_app
    USING (TRUE);

-- Invoice : un client ne voit que ses propres factures
CREATE POLICY policy_invoice_own
    ON invoice FOR SELECT
    TO gemlink_app
    USING (
        client_id IN (
            SELECT id FROM vendeur
            WHERE user_id = current_setting('app.current_user_id')::UUID
        )
    );

-- gemlink_admin : bypass de toutes les politiques RLS
ALTER ROLE gemlink_admin BYPASSRLS;
```

---

## 7. Résumé des relations

| Table source | Colonne FK | Table cible | Cardinalité | ON DELETE |
|---|---|---|---|---|
| `vendeur` | `user_id` | `user` | 1–1 | CASCADE |
| `refresh_token` | `user_id` | `user` | N–1 | CASCADE |
| `post` | `user_id` | `user` | N–1 | CASCADE |
| `post` | `stone_id` | `stone` | N–0..1 | SET NULL |
| `post_tag` | `post_id` | `post` | N–1 | CASCADE |
| `post_tag` | `tag_id` | `tag` | N–1 | CASCADE |
| `comment` | `post_id` | `post` | N–1 | CASCADE |
| `comment` | `user_id` | `user` | N–1 | CASCADE |
| `like` | `post_id` | `post` | N–1 | CASCADE |
| `like` | `user_id` | `user` | N–1 | CASCADE |
| `embedding` | `post_id` | `post` | 1–1 | CASCADE |
| `embedding` | `model_version_id` | `ai_model_version` | N–1 | RESTRICT |
| `fine_tune_job` | `model_version_id` | `ai_model_version` | N–1 | RESTRICT |
| `validation` | `post_id` | `post` | N–1 | CASCADE |
| `validation` | `user_id` | `user` | N–1 | CASCADE |
| `vitrine` | `user_id` | `user` | N–1 | CASCADE |
| `vitrine_item` | `vitrine_id` | `vitrine` | N–1 | CASCADE |
| `vitrine_item` | `post_id` | `post` | N–1 | CASCADE |
| `badge` | `created_by` | `user` | N–0..1 | SET NULL |
| `user_badge` | `user_id` | `user` | N–1 | CASCADE |
| `user_badge` | `badge_id` | `badge` | N–1 | CASCADE |
| `notification` | `user_id` | `user` | N–1 | CASCADE |
| `point_transaction` | `user_id` | `user` | N–1 | CASCADE |
| `report` | `post_id` | `post` | N–1 | CASCADE |
| `report` | `reporter_id` | `user` | N–1 | CASCADE |
| `group_entity` | `owner_id` | `user` | N–1 | CASCADE |
| `group_member` | `group_id` | `group_entity` | N–1 | CASCADE |
| `group_member` | `user_id` | `user` | N–1 | CASCADE |
| `follow` | `follower_id` | `user` | N–1 | CASCADE |
| `follow` | `followed_id` | `user` | N–1 | CASCADE |
| `invoice` | `client_id` | `vendeur` | N–1 | RESTRICT |
| `audit_log` | `actor_id` | `user` | N–1 | RESTRICT |