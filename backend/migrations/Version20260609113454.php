<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609113454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial GemLink corrigé : Tables, Types, Index avancés et Triggers alignés ORM.';
    }

    public function up(Schema $schema): void
    {
        // ─────────────────────────────────────────────
        // 0. EXTENSIONS & TYPES ÉNUMÉRÉS (ENUM)
        // ─────────────────────────────────────────────
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "vector"');

        $this->addSql("CREATE TYPE user_status AS ENUM ('PENDING_VALIDATION', 'ACTIVE', 'BANNED')");
        $this->addSql("CREATE TYPE user_role AS ENUM ('USER', 'EXPERT', 'MODERATOR', 'VENDEUR', 'ADMIN')");
        $this->addSql("CREATE TYPE post_status AS ENUM ('PENDING_ANALYSIS', 'ANALYZED', 'ANALYSIS_FAILED', 'AUTO_HIDDEN', 'PUBLISHED')");
        $this->addSql("CREATE TYPE media_type AS ENUM ('IMAGE', 'VIDEO')");
        $this->addSql("CREATE TYPE validation_action AS ENUM ('CONFIRM', 'CORRECT', 'REJECT')");
        $this->addSql("CREATE TYPE report_reason AS ENUM ('INAPPROPRIATE_CONTENT', 'WRONG_IDENTIFICATION', 'SPAM', 'HARASSMENT')");
        $this->addSql("CREATE TYPE report_status AS ENUM ('PENDING', 'ACCEPTED', 'REJECTED')");
        $this->addSql("CREATE TYPE badge_condition_type AS ENUM ('POST_COUNT', 'VALIDATION_COUNT', 'TRUST_SCORE_THRESHOLD', 'LEVEL_REACHED')");
        $this->addSql("CREATE TYPE point_action_type AS ENUM ('POST_PUBLISHED', 'LIKE_RECEIVED', 'VALIDATION_SUBMITTED', 'VALIDATION_CONFIRMED')");
        $this->addSql("CREATE TYPE ai_model_type AS ENUM ('YOLO', 'VIT', 'CLIP')");
        $this->addSql("CREATE TYPE ai_model_status AS ENUM ('TRAINING', 'ACTIVE', 'DEPRECATED')");
        $this->addSql("CREATE TYPE fine_tune_status AS ENUM ('PENDING', 'RUNNING', 'COMPLETED', 'FAILED')");
        $this->addSql("CREATE TYPE group_visibility AS ENUM ('PUBLIC', 'PRIVATE')");
        $this->addSql("CREATE TYPE invoice_status AS ENUM ('PENDING', 'PAID', 'CANCELLED')");
        $this->addSql("CREATE TYPE tag_scope AS ENUM ('GLOBAL', 'VENDEUR', 'USER')");

        // ─────────────────────────────────────────────
        // 1. UTILISATEUR
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE utilisateur (
                id              UUID            NOT NULL DEFAULT uuid_generate_v4(),
                username        VARCHAR(30)     NOT NULL DEFAULT \'\',
                email           VARCHAR(255)    NOT NULL DEFAULT \'\',
                password_hash   VARCHAR(255)    NOT NULL DEFAULT \'\',
                avatar_url      TEXT,
                bio             VARCHAR(500),
                trust_score     SMALLINT        NOT NULL DEFAULT 0 CHECK (trust_score BETWEEN 0 AND 100),
                role            user_role       NOT NULL DEFAULT \'USER\',
                points          INTEGER         NOT NULL DEFAULT 0 CHECK (points >= 0),
                level           SMALLINT        NOT NULL DEFAULT 1 CHECK (level >= 1),
                status          user_status     NOT NULL DEFAULT \'PENDING_VALIDATION\',
                created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_utilisateur_email UNIQUE (email),
                CONSTRAINT uq_utilisateur_username UNIQUE (username)
            )
        ');
        $this->addSql('CREATE INDEX idx_utilisateur_role     ON utilisateur (role)');
        $this->addSql('CREATE INDEX idx_utilisateur_status   ON utilisateur (status)');
        $this->addSql('CREATE INDEX idx_utilisateur_points   ON utilisateur (points DESC)');

        // ─────────────────────────────────────────────
        // 2. VENDEUR 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE vendeur (
                id                      UUID            NOT NULL DEFAULT uuid_generate_v4(),
                user_id                 UUID            NOT NULL,
                company_name            VARCHAR(150)    NOT NULL DEFAULT \'\',
                siret                   VARCHAR(14)     NOT NULL DEFAULT \'\',
                address                 TEXT,
                subscription_plan       VARCHAR(50),
                subscription_expires_at TIMESTAMPTZ,
                created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_vendeur_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE RESTRICT,
                CONSTRAINT uq_vendeur_user UNIQUE (user_id),
                CONSTRAINT uq_vendeur_siret UNIQUE (siret)
            )
        ');

        // ─────────────────────────────────────────────
        // 3. REFRESH_TOKEN
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE refresh_token (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id     UUID        NOT NULL,
                token_hash  VARCHAR(64) NOT NULL DEFAULT \'\',
                expires_at  TIMESTAMPTZ NOT NULL,
                revoked_at  TIMESTAMPTZ,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_refresh_token_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE,
                CONSTRAINT uq_refresh_token_hash UNIQUE (token_hash)
            )
        ');
        $this->addSql('CREATE INDEX idx_refresh_token_user_id ON refresh_token (user_id)');

        // ─────────────────────────────────────────────
        // 4. PIERRE 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE pierre (
                id             UUID            NOT NULL DEFAULT uuid_generate_v4(),
                name           VARCHAR(100)    NOT NULL DEFAULT \'\',
                category       VARCHAR(100),
                hardness       NUMERIC(4, 2)   CHECK (hardness BETWEEN 0 AND 10),
                crystal_system VARCHAR(50),
                composition    TEXT,
                description    TEXT,
                created_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_pierre_name UNIQUE (name)
            )
        ');

        // ─────────────────────────────────────────────
        // 5. TAG
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE tag (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                owner_id    UUID,
                name        VARCHAR(50) NOT NULL DEFAULT \'\',
                scope       tag_scope   NOT NULL DEFAULT \'GLOBAL\',
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_tag_owner FOREIGN KEY (owner_id) REFERENCES utilisateur (id) ON DELETE SET NULL,
                CONSTRAINT uq_tag_name_scope_owner UNIQUE (name, scope, owner_id)
            )
        ');

        // ─────────────────────────────────────────────
        // 6. PUBLICATION 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE publication (
                id           UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id      UUID        NOT NULL,
                title        VARCHAR(200),
                description  TEXT,
                media_url    TEXT        NOT NULL DEFAULT \'\',
                media_type   media_type  NOT NULL,
                status       post_status NOT NULL DEFAULT \'PENDING_ANALYSIS\',
                is_sponsored BOOLEAN     NOT NULL DEFAULT FALSE,
                view_count   INTEGER     NOT NULL DEFAULT 0 CHECK (view_count >= 0),
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                deleted_at   TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT fk_publication_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_publication_user_id    ON publication (user_id)');
        $this->addSql('CREATE INDEX idx_publication_status     ON publication (status)');
        $this->addSql('CREATE INDEX idx_publication_created_at ON publication (created_at DESC)');

        // ─────────────────────────────────────────────
        // 7. PUBLICATION_TAG
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE publication_tag (
                publication_id UUID NOT NULL,
                tag_id         UUID NOT NULL,
                PRIMARY KEY (publication_id, tag_id),
                CONSTRAINT fk_pub_tag_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE,
                CONSTRAINT fk_pub_tag_tag FOREIGN KEY (tag_id) REFERENCES tag(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 8. COMMENTAIRE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE commentaire (
                id             UUID          NOT NULL DEFAULT uuid_generate_v4(),
                publication_id UUID          NOT NULL,
                user_id        UUID          NOT NULL,
                content        TEXT          NOT NULL DEFAULT \'\',
                created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                updated_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                deleted_at     TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT fk_commentaire_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE,
                CONSTRAINT fk_commentaire_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_commentaire_pub_id ON commentaire (publication_id)');

        // ─────────────────────────────────────────────
        // 9. PUBLICATION_PIERRE 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE publication_pierre (
                publication_id UUID          NOT NULL,
                pierre_id      UUID          NOT NULL,
                confidence     NUMERIC(5, 4) NOT NULL CHECK (confidence BETWEEN 0 AND 1),
                PRIMARY KEY (publication_id, pierre_id),
                CONSTRAINT fk_pub_pierre_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE,
                CONSTRAINT fk_pub_pierre_pierre FOREIGN KEY (pierre_id) REFERENCES pierre(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 10. AI_MODEL_VERSION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE ai_model_version (
                id          UUID            NOT NULL DEFAULT uuid_generate_v4(),
                name        VARCHAR(50)     NOT NULL DEFAULT \'\',
                model_type  ai_model_type   NOT NULL,
                accuracy    NUMERIC(5, 4)   CHECK (accuracy BETWEEN 0 AND 1),
                f1_score    NUMERIC(5, 4)   CHECK (f1_score BETWEEN 0 AND 1),
                description TEXT,
                status      ai_model_status NOT NULL DEFAULT \'TRAINING\',
                created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_ai_model_version_name UNIQUE (name)
            )
        ');

        // ─────────────────────────────────────────────
        // 11. EMBEDDING (pgvector HNSW)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE embedding (
                id                  UUID          NOT NULL DEFAULT uuid_generate_v4(),
                publication_id      UUID          NOT NULL,
                version_modele_ia_id UUID          NOT NULL,
                vector_data         vector(512)   NOT NULL,
                created_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_embedding_publication UNIQUE (publication_id),
                CONSTRAINT fk_embedding_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE,
                CONSTRAINT fk_embedding_modele FOREIGN KEY (version_modele_ia_id) REFERENCES ai_model_version(id) ON DELETE RESTRICT
            )
        ');
        $this->addSql('CREATE INDEX idx_embedding_vector_hnsw ON embedding USING hnsw (vector_data vector_cosine_ops)');

        // ─────────────────────────────────────────────
        // 12. JOB_FINE_TUNING
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE job_fine_tuning (
                id                  UUID              NOT NULL DEFAULT uuid_generate_v4(),
                version_modele_ia_id UUID              NOT NULL,
                min_trust_score     SMALLINT          NOT NULL CHECK (min_trust_score BETWEEN 0 AND 100),
                status              fine_tune_status  NOT NULL DEFAULT \'PENDING\',
                created_at          TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_job_fine_tuning_modele FOREIGN KEY (version_modele_ia_id) REFERENCES ai_model_version(id) ON DELETE RESTRICT
            )
        ');

        // ─────────────────────────────────────────────
        // 13. VALIDATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE validation (
                id                    UUID              NOT NULL DEFAULT uuid_generate_v4(),
                publication_id        UUID              NOT NULL,
                user_id               UUID              NOT NULL,
                pierre_id             UUID              NOT NULL,
                action                validation_action NOT NULL,
                proposed_label        VARCHAR(100),
                trust_score_snapshot  SMALLINT          NOT NULL CHECK (trust_score_snapshot BETWEEN 0 AND 100),
                created_at            TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_validation_pub_user UNIQUE (publication_id, user_id),
                CONSTRAINT fk_validation_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE,
                CONSTRAINT fk_validation_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_validation_pierre FOREIGN KEY (pierre_id) REFERENCES pierre(id) ON DELETE RESTRICT
            )
        ');

        // ─────────────────────────────────────────────
        // 14. VITRINE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE vitrine (
                id           UUID         NOT NULL DEFAULT uuid_generate_v4(),
                user_id      UUID         NOT NULL,
                title        VARCHAR(100) NOT NULL DEFAULT \'\',
                description  TEXT,
                slug         VARCHAR(150) NOT NULL DEFAULT \'\',
                view_count   INTEGER      NOT NULL DEFAULT 0,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_vitrine_slug UNIQUE (slug),
                CONSTRAINT fk_vitrine_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 15. VITRINE_PUBLICATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE vitrine_publication (
                vitrine_id     UUID        NOT NULL,
                publication_id UUID        NOT NULL,
                added_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (vitrine_id, publication_id),
                CONSTRAINT fk_vitrine_pub_vitrine FOREIGN KEY (vitrine_id) REFERENCES vitrine(id) ON DELETE CASCADE,
                CONSTRAINT fk_vitrine_pub_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 16. BADGE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE badge (
                id              UUID                 NOT NULL DEFAULT uuid_generate_v4(),
                name            VARCHAR(100)         NOT NULL DEFAULT \'\',
                description     TEXT,
                condition_type  badge_condition_type NOT NULL,
                condition_value INTEGER              NOT NULL,
                created_at      TIMESTAMPTZ          NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_badge_name UNIQUE (name)
            )
        ');

        // ─────────────────────────────────────────────
        // 17. USER_BADGE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE user_badge (
                user_id   UUID        NOT NULL,
                badge_id  UUID        NOT NULL,
                earned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, badge_id),
                CONSTRAINT fk_user_badge_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_badge_badge FOREIGN KEY (badge_id) REFERENCES badge(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 18. NOTIFICATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE notification (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id     UUID        NOT NULL,
                type        VARCHAR(50) NOT NULL DEFAULT \'\',
                target_id   UUID        NOT NULL,
                target_type VARCHAR(50) NOT NULL DEFAULT \'\',
                is_read     BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_notification_unread ON notification (user_id) WHERE is_read = FALSE');

        // ─────────────────────────────────────────────
        // 19. REPORT 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE report (
                id             UUID          NOT NULL DEFAULT uuid_generate_v4(),
                user_id        UUID          NOT NULL, 
                publication_id UUID          NOT NULL, 
                reason_type    report_reason NOT NULL,
                description    TEXT,
                status         report_status NOT NULL DEFAULT \'PENDING\',
                created_at     TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_report_user_publication UNIQUE (user_id, publication_id),
                CONSTRAINT fk_report_utilisateur FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_report_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_report_status ON report (status)');

        // ─────────────────────────────────────────────
        // 20. GROUPE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE groupe (
                id          UUID             NOT NULL DEFAULT uuid_generate_v4(),
                name        VARCHAR(100)     NOT NULL DEFAULT \'\',
                description TEXT,
                visibility  group_visibility NOT NULL DEFAULT \'PUBLIC\',
                created_by  UUID             NOT NULL,
                created_at  TIMESTAMPTZ      NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_groupe_name UNIQUE (name),
                CONSTRAINT fk_groupe_createur FOREIGN KEY (created_by) REFERENCES utilisateur(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 21. UTILISATEUR_GROUPE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE utilisateur_groupe (
                user_id   UUID        NOT NULL,
                groupe_id UUID        NOT NULL,
                role      VARCHAR(50) NOT NULL DEFAULT \'member\',
                joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, groupe_id),
                CONSTRAINT fk_util_group_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_util_group_groupe FOREIGN KEY (groupe_id) REFERENCES groupe(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 22. UTILISATEUR_AIME_PUBLICATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE utilisateur_aime_publication (
                user_id        UUID        NOT NULL,
                publication_id UUID        NOT NULL,
                created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, publication_id),
                CONSTRAINT fk_aime_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_aime_publication FOREIGN KEY (publication_id) REFERENCES publication(id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 23. FACTURE 
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE facture (
                id         UUID           NOT NULL DEFAULT uuid_generate_v4(),
                vendeur_id UUID           NOT NULL,
                amount     NUMERIC(10, 2) NOT NULL CHECK (amount > 0),
                content    TEXT,
                status     invoice_status NOT NULL DEFAULT \'PENDING\',
                created_at TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_facture_vendeur FOREIGN KEY (vendeur_id) REFERENCES vendeur(id) ON DELETE RESTRICT
            )
        ');

        // ─────────────────────────────────────────────
        // 24. AUDIT_LOG (Journalisation Immuable)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE audit_log (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id     UUID        NOT NULL,
                action      VARCHAR(50) NOT NULL DEFAULT \'\',
                target_type VARCHAR(50) NOT NULL DEFAULT \'\',
                target_id   UUID        NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE RESTRICT
            )
        ');

        // ─────────────────────────────────────────────
        // 24 BIS. EMAIL_VALIDATION_TOKEN (US 1.1)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE email_validation_token (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id     UUID        NOT NULL,
                token       VARCHAR(255) NOT NULL DEFAULT \'\',
                expires_at  TIMESTAMPTZ NOT NULL,
                used        BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_email_validation_token_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE,
                CONSTRAINT uq_email_validation_token_token UNIQUE (token)
            )
        ');
        $this->addSql('CREATE INDEX idx_email_validation_token_user_id ON email_validation_token (user_id)');

        // ─────────────────────────────────────────────
        // 24 TER. PASSWORD_RESET_TOKEN
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE password_reset_token (
                id          UUID        NOT NULL DEFAULT uuid_generate_v4(),
                user_id     UUID        NOT NULL,
                token       VARCHAR(255) NOT NULL DEFAULT \'\',
                expires_at  TIMESTAMPTZ NOT NULL,
                used        BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_password_reset_token_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE,
                CONSTRAINT uq_password_reset_token_token UNIQUE (token)
            )
        ');
        $this->addSql('CREATE INDEX idx_password_reset_token_user_id ON password_reset_token (user_id)');

        // ─────────────────────────────────────────────
        // 24 QUATER. NEWSLETTER_SUBSCRIBER
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE newsletter_subscriber (
                id               UUID        NOT NULL DEFAULT uuid_generate_v4(),
                email            VARCHAR(255) NOT NULL DEFAULT \'\',
                status           VARCHAR(20) NOT NULL DEFAULT \'ACTIVE\',
                subscribed_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                unsubscribed_at  TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT uq_newsletter_subscriber_email UNIQUE (email)
            )
        ');
        $this->addSql('CREATE INDEX idx_newsletter_subscriber_status ON newsletter_subscriber (status)');

        // ─────────────────────────────────────────────
        // 25. INDEX RECHERCHE AVANCÉE (FTS & FEED)
        // ─────────────────────────────────────────────
        $this->addSql("CREATE INDEX idx_publication_fts ON publication USING gin(to_tsvector('french', COALESCE(title, '') || ' ' || COALESCE(description, ''))) WHERE deleted_at IS NULL");
        $this->addSql("CREATE INDEX idx_pierre_fts ON pierre USING gin(to_tsvector('french', name || ' ' || COALESCE(description, '')))");
        $this->addSql("CREATE INDEX idx_publication_feed ON publication (status, deleted_at, created_at DESC, id) WHERE deleted_at IS NULL AND status = 'PUBLISHED'");

        // ─────────────────────────────────────────────
        // 26. TRIGGERS POSTGRESQL (Automatisation Métier)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE OR REPLACE FUNCTION trigger_set_timestamp() RETURNS TRIGGER AS $$
            BEGIN NEW.updated_at = NOW(); RETURN NEW; END; $$ LANGUAGE plpgsql;
        ');
        $this->addSql('CREATE TRIGGER set_timestamp_user BEFORE UPDATE ON utilisateur FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp()');
        $this->addSql('CREATE TRIGGER set_timestamp_publication BEFORE UPDATE ON publication FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp()');
        $this->addSql('CREATE TRIGGER set_timestamp_commentaire BEFORE UPDATE ON commentaire FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp()');
        $this->addSql('CREATE TRIGGER set_timestamp_vitrine BEFORE UPDATE ON vitrine FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp()');

        $this->addSql('
            CREATE OR REPLACE FUNCTION trigger_auto_moderate_publication() RETURNS TRIGGER AS $$
            DECLARE report_count INTEGER;
            BEGIN
                SELECT COUNT(*) INTO report_count FROM report WHERE publication_id = NEW.publication_id AND status != \'REJECTED\';
                IF report_count >= 5 THEN
                    UPDATE publication SET status = \'AUTO_HIDDEN\', updated_at = NOW() WHERE id = NEW.publication_id AND status = \'PUBLISHED\';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
        ');
        $this->addSql('CREATE TRIGGER auto_moderate_pub_after_report AFTER INSERT OR UPDATE ON report FOR EACH ROW EXECUTE FUNCTION trigger_auto_moderate_publication()');

        $this->addSql('
            CREATE OR REPLACE FUNCTION trigger_recalculate_trust_score() RETURNS TRIGGER AS $$
            DECLARE 
                target_user_id UUID; 
                v_confirmed INTEGER; 
                v_total INTEGER; 
                new_score SMALLINT;
                ref_pub_id UUID;
            BEGIN
                IF TG_OP = \'DELETE\' THEN
                    ref_pub_id := OLD.publication_id;
                ELSE
                    ref_pub_id := NEW.publication_id;
                END IF;

                SELECT user_id INTO target_user_id FROM publication WHERE id = ref_pub_id;
                
                SELECT COUNT(*) FILTER (WHERE action = \'CONFIRM\'), COUNT(*) 
                INTO v_confirmed, v_total 
                FROM validation v 
                JOIN publication p ON v.publication_id = p.id 
                WHERE p.user_id = target_user_id;
                
                IF v_total > 0 THEN
                    new_score := LEAST(100, GREATEST(0, (v_confirmed * 100) / v_total));
                ELSE
                    new_score := 0;
                END IF;
                
                UPDATE utilisateur SET trust_score = new_score WHERE id = target_user_id;
                
                IF TG_OP = \'DELETE\' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
        ');
        $this->addSql('CREATE TRIGGER update_trust_score_after_validation AFTER INSERT OR UPDATE OR DELETE ON validation FOR EACH ROW EXECUTE FUNCTION trigger_recalculate_trust_score()');

        $this->addSql('
            CREATE OR REPLACE FUNCTION trigger_protect_audit_log() RETURNS TRIGGER AS $$
            BEGIN RAISE EXCEPTION \'Interdiction formelle de modifier ou supprimer un enregistrement du journal d’audit.\'; RETURN NULL; END; $$ LANGUAGE plpgsql;
        ');
        $this->addSql('CREATE TRIGGER protect_audit_log_modifications BEFORE UPDATE OR DELETE ON audit_log FOR EACH ROW EXECUTE FUNCTION trigger_protect_audit_log()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS protect_audit_log_modifications ON audit_log');
        $this->addSql('DROP TRIGGER IF EXISTS update_trust_score_after_validation ON validation');
        $this->addSql('DROP TRIGGER IF EXISTS auto_moderate_pub_after_report ON report');
        $this->addSql('DROP TRIGGER IF EXISTS set_timestamp_vitrine ON vitrine');
        $this->addSql('DROP TRIGGER IF EXISTS set_timestamp_commentaire ON commentaire');
        $this->addSql('DROP TRIGGER IF EXISTS set_timestamp_publication ON publication');
        $this->addSql('DROP TRIGGER IF EXISTS set_timestamp_user ON utilisateur');
        $this->addSql('DROP FUNCTION IF EXISTS trigger_protect_audit_log');
        $this->addSql('DROP FUNCTION IF EXISTS trigger_recalculate_trust_score');
        $this->addSql('DROP FUNCTION IF EXISTS trigger_auto_moderate_publication');
        $this->addSql('DROP FUNCTION IF EXISTS trigger_set_timestamp');

        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS facture');
        $this->addSql('DROP TABLE IF EXISTS utilisateur_aime_publication');
        $this->addSql('DROP TABLE IF EXISTS utilisateur_groupe');
        $this->addSql('DROP TABLE IF EXISTS groupe');
        $this->addSql('DROP TABLE IF EXISTS report');
        $this->addSql('DROP TABLE IF EXISTS notification');
        $this->addSql('DROP TABLE IF EXISTS user_badge');
        $this->addSql('DROP TABLE IF EXISTS badge');
        $this->addSql('DROP TABLE IF EXISTS vitrine_publication');
        $this->addSql('DROP TABLE IF EXISTS vitrine');
        $this->addSql('DROP TABLE IF EXISTS validation');
        $this->addSql('DROP TABLE IF EXISTS job_fine_tuning');
        $this->addSql('DROP TABLE IF EXISTS embedding');
        $this->addSql('DROP TABLE IF EXISTS ai_model_version');
        $this->addSql('DROP TABLE IF EXISTS publication_pierre');
        $this->addSql('DROP TABLE IF EXISTS commentaire');
        $this->addSql('DROP TABLE IF EXISTS publication_tag');
        $this->addSql('DROP TABLE IF EXISTS publication');
        $this->addSql('DROP TABLE IF EXISTS tag');
        $this->addSql('DROP TABLE IF EXISTS pierre');
        $this->addSql('DROP TABLE IF EXISTS newsletter_subscriber');
        $this->addSql('DROP TABLE IF EXISTS password_reset_token');
        $this->addSql('DROP TABLE IF EXISTS email_validation_token');
        $this->addSql('DROP TABLE IF EXISTS refresh_token');
        $this->addSql('DROP TABLE IF EXISTS vendeur');
        $this->addSql('DROP TABLE IF EXISTS utilisateur');

        $this->addSql('DROP TYPE IF EXISTS tag_scope');
        $this->addSql('DROP TYPE IF EXISTS invoice_status');
        $this->addSql('DROP TYPE IF EXISTS group_visibility');
        $this->addSql('DROP TYPE IF EXISTS fine_tune_status');
        $this->addSql('DROP TYPE IF EXISTS ai_model_status');
        $this->addSql('DROP TYPE IF EXISTS ai_model_type');
        $this->addSql('DROP TYPE IF EXISTS point_action_type');
        $this->addSql('DROP TYPE IF EXISTS badge_condition_type');
        $this->addSql('DROP TYPE IF EXISTS report_status');
        $this->addSql('DROP TYPE IF EXISTS report_reason');
        $this->addSql('DROP TYPE IF EXISTS validation_action');
        $this->addSql('DROP TYPE IF EXISTS media_type');
        $this->addSql('DROP TYPE IF EXISTS post_status');
        $this->addSql('DROP TYPE IF EXISTS user_role');
        $this->addSql('DROP TYPE IF EXISTS user_status');
    }
}