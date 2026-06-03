<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration initiale GemLink — Schéma complet
 * Ordre de création respectant les dépendances FK
 */
final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial GemLink : toutes les tables, index, contraintes et extension pgvector';
    }

    public function up(Schema $schema): void
    {
        // Extension pgvector (doit être installée sur le serveur PostgreSQL)
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "vector"');

        // ─────────────────────────────────────────────
        // 1. USER
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE "user" (
                id          UUID        NOT NULL DEFAULT gen_random_uuid(),
                username    VARCHAR(30) NOT NULL,
                email       VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                avatar_url  TEXT,
                bio         VARCHAR(500),
                trust_score SMALLINT    NOT NULL DEFAULT 0
                            CHECK (trust_score BETWEEN 0 AND 100),
                role        VARCHAR(20) NOT NULL DEFAULT \'user\'
                            CHECK (role IN (\'user\',\'expert\',\'moderator\',\'client\',\'admin\')),
                points      INTEGER     NOT NULL DEFAULT 0,
                level       SMALLINT    NOT NULL DEFAULT 1,
                status      VARCHAR(25) NOT NULL DEFAULT \'PENDING_VALIDATION\'
                            CHECK (status IN (\'PENDING_VALIDATION\',\'ACTIVE\',\'BANNED\')),
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_user_email    ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX idx_user_username ON "user" (username)');
        $this->addSql('CREATE INDEX idx_user_role   ON "user" (role)');
        $this->addSql('CREATE INDEX idx_user_status ON "user" (status)');

        // ─────────────────────────────────────────────
        // 2. CLIENT_PROFILE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE client_profile (
                id                      UUID        NOT NULL DEFAULT gen_random_uuid(),
                user_id                 UUID        NOT NULL,
                company_name            VARCHAR(150) NOT NULL,
                siret                   VARCHAR(14),
                address                 TEXT,
                subscription_plan       VARCHAR(50),
                subscription_expires_at TIMESTAMPTZ,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_client_profile_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_client_profile_user_id ON client_profile (user_id)');

        // ─────────────────────────────────────────────
        // 3. CLIENT_CUSTOMER
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE client_customer (
                client_id          UUID        NOT NULL,
                customer_user_id   UUID        NOT NULL,
                created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (client_id, customer_user_id),
                CONSTRAINT fk_cc_client
                    FOREIGN KEY (client_id) REFERENCES client_profile (id) ON DELETE CASCADE,
                CONSTRAINT fk_cc_user
                    FOREIGN KEY (customer_user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_client_customer_client_id ON client_customer (client_id)');

        // ─────────────────────────────────────────────
        // 4. STONE (catalogue minéraux)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE stone (
                id             UUID           NOT NULL DEFAULT gen_random_uuid(),
                name           VARCHAR(100)   NOT NULL,
                category       VARCHAR(100),
                hardness       NUMERIC(4,2),
                crystal_system VARCHAR(50),
                composition    TEXT,
                description    TEXT,
                PRIMARY KEY (id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_stone_name     ON stone (name)');
        $this->addSql('CREATE INDEX        idx_stone_category ON stone (category)');

        // ─────────────────────────────────────────────
        // 5. AI_MODEL_VERSION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE ai_model_version (
                id         UUID        NOT NULL DEFAULT gen_random_uuid(),
                name       VARCHAR(50) NOT NULL,
                model_type VARCHAR(10) NOT NULL
                           CHECK (model_type IN (\'YOLO\',\'VIT\',\'CLIP\')),
                accuracy   NUMERIC(5,4),
                f1_score   NUMERIC(5,4),
                status     VARCHAR(15) NOT NULL DEFAULT \'TRAINING\'
                           CHECK (status IN (\'TRAINING\',\'ACTIVE\',\'DEPRECATED\')),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_ai_model_name ON ai_model_version (name)');

        // ─────────────────────────────────────────────
        // 6. POST
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE post (
                id           UUID        NOT NULL DEFAULT gen_random_uuid(),
                user_id      UUID        NOT NULL,
                stone_id     UUID,
                title        VARCHAR(200),
                description  TEXT,
                media_url    TEXT        NOT NULL,
                media_type   VARCHAR(10) NOT NULL
                             CHECK (media_type IN (\'image\',\'video\')),
                status       VARCHAR(20) NOT NULL DEFAULT \'PENDING_ANALYSIS\'
                             CHECK (status IN (
                                \'PENDING_ANALYSIS\',\'ANALYZED\',\'ANALYSIS_FAILED\',
                                \'AUTO_HIDDEN\',\'PUBLISHED\'
                             )),
                is_sponsored BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                deleted_at   TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT fk_post_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT fk_post_stone
                    FOREIGN KEY (stone_id) REFERENCES stone (id) ON DELETE SET NULL
            )
        ');
        $this->addSql('CREATE INDEX idx_post_user_id    ON post (user_id)');
        $this->addSql('CREATE INDEX idx_post_status     ON post (status)');
        $this->addSql('CREATE INDEX idx_post_created_at ON post (created_at DESC)');
        $this->addSql('CREATE INDEX idx_post_not_deleted ON post (created_at DESC) WHERE deleted_at IS NULL');

        // ─────────────────────────────────────────────
        // 7. COMMENT
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE comment (
                id         UUID          NOT NULL DEFAULT gen_random_uuid(),
                post_id    UUID          NOT NULL,
                user_id    UUID          NOT NULL,
                content    VARCHAR(1000) NOT NULL,
                created_at TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
                deleted_at TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT fk_comment_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_comment_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_comment_post_id ON comment (post_id)');
        $this->addSql('CREATE INDEX idx_comment_user_id ON comment (user_id)');

        // ─────────────────────────────────────────────
        // 8. LIKE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE "like" (
                id         UUID        NOT NULL DEFAULT gen_random_uuid(),
                post_id    UUID        NOT NULL,
                user_id    UUID        NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_like_post_user UNIQUE (post_id, user_id),
                CONSTRAINT fk_like_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_like_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_like_post_id ON "like" (post_id)');
        $this->addSql('CREATE INDEX idx_like_user_id ON "like" (user_id)');

        // ─────────────────────────────────────────────
        // 9. TAG
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE tag (
                id       UUID        NOT NULL DEFAULT gen_random_uuid(),
                owner_id UUID,
                name     VARCHAR(50) NOT NULL,
                scope    VARCHAR(20) NOT NULL DEFAULT \'global\'
                         CHECK (scope IN (\'global\',\'client\',\'user\')),
                PRIMARY KEY (id),
                CONSTRAINT fk_tag_owner
                    FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL
            )
        ');
        $this->addSql('CREATE INDEX idx_tag_name     ON tag (name)');
        $this->addSql('CREATE INDEX idx_tag_owner_id ON tag (owner_id)');

        // ─────────────────────────────────────────────
        // 10. POST_TAG
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE post_tag (
                post_id UUID NOT NULL,
                tag_id  UUID NOT NULL,
                PRIMARY KEY (post_id, tag_id),
                CONSTRAINT fk_post_tag_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_post_tag_tag
                    FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 11. EMBEDDING (pgvector)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE embedding (
                id               UUID        NOT NULL DEFAULT gen_random_uuid(),
                post_id          UUID        NOT NULL,
                model_version_id UUID        NOT NULL,
                vector_data      vector(512) NOT NULL,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_embedding_post UNIQUE (post_id),
                CONSTRAINT fk_embedding_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_embedding_model
                    FOREIGN KEY (model_version_id) REFERENCES ai_model_version (id)
            )
        ');
        // Index HNSW pour la recherche par similarité cosinus
        $this->addSql('
            CREATE INDEX idx_embedding_vector_hnsw
                ON embedding USING hnsw (vector_data vector_cosine_ops)
                WITH (m = 16, ef_construction = 64)
        ');

        // ─────────────────────────────────────────────
        // 12. VALIDATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE validation (
                id                  UUID        NOT NULL DEFAULT gen_random_uuid(),
                post_id             UUID        NOT NULL,
                user_id             UUID        NOT NULL,
                action              VARCHAR(10) NOT NULL
                                    CHECK (action IN (\'confirm\',\'correct\',\'reject\')),
                proposed_label      VARCHAR(100),
                trust_score_snapshot SMALLINT   NOT NULL,
                created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_validation_post_user UNIQUE (post_id, user_id),
                CONSTRAINT fk_validation_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_validation_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_validation_post_id ON validation (post_id)');
        $this->addSql('CREATE INDEX idx_validation_user_id ON validation (user_id)');

        // ─────────────────────────────────────────────
        // 13. VITRINE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE vitrine (
                id           UUID         NOT NULL DEFAULT gen_random_uuid(),
                user_id      UUID         NOT NULL,
                title        VARCHAR(100) NOT NULL,
                description  VARCHAR(500),
                slug         VARCHAR(150) NOT NULL,
                qr_code_url  TEXT,
                view_count   INTEGER      NOT NULL DEFAULT 0,
                is_sponsored BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_vitrine_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_vitrine_slug    ON vitrine (slug)');
        $this->addSql('CREATE INDEX        idx_vitrine_user_id ON vitrine (user_id)');

        // ─────────────────────────────────────────────
        // 14. VITRINE_ITEM
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE vitrine_item (
                id         UUID    NOT NULL DEFAULT gen_random_uuid(),
                vitrine_id UUID    NOT NULL,
                post_id    UUID    NOT NULL,
                position   INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                CONSTRAINT uq_vitrine_item UNIQUE (vitrine_id, post_id),
                CONSTRAINT fk_vitrine_item_vitrine
                    FOREIGN KEY (vitrine_id) REFERENCES vitrine (id) ON DELETE CASCADE,
                CONSTRAINT fk_vitrine_item_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_vitrine_item_vitrine_id ON vitrine_item (vitrine_id)');

        // ─────────────────────────────────────────────
        // 15. BADGE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE badge (
                id              UUID         NOT NULL DEFAULT gen_random_uuid(),
                created_by      UUID,
                name            VARCHAR(100) NOT NULL,
                description     TEXT,
                icon_url        TEXT,
                condition_type  VARCHAR(30)  NOT NULL
                                CHECK (condition_type IN (
                                    \'POST_COUNT\',\'VALIDATION_COUNT\',
                                    \'TRUST_SCORE_THRESHOLD\',\'LEVEL_REACHED\'
                                )),
                condition_value INTEGER      NOT NULL,
                is_custom       BOOLEAN      NOT NULL DEFAULT FALSE,
                PRIMARY KEY (id),
                CONSTRAINT fk_badge_creator
                    FOREIGN KEY (created_by) REFERENCES "user" (id) ON DELETE SET NULL
            )
        ');

        // ─────────────────────────────────────────────
        // 16. USER_BADGE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE user_badge (
                user_id   UUID        NOT NULL,
                badge_id  UUID        NOT NULL,
                earned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, badge_id),
                CONSTRAINT fk_user_badge_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT fk_user_badge_badge
                    FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 17. REPORT
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE report (
                id          UUID        NOT NULL DEFAULT gen_random_uuid(),
                post_id     UUID        NOT NULL,
                reporter_id UUID        NOT NULL,
                reason_type VARCHAR(40) NOT NULL
                            CHECK (reason_type IN (
                                \'INAPPROPRIATE_CONTENT\',\'WRONG_IDENTIFICATION\',
                                \'SPAM\',\'HARASSMENT\'
                            )),
                description TEXT,
                status      VARCHAR(20) NOT NULL DEFAULT \'PENDING\'
                            CHECK (status IN (\'PENDING\',\'ACCEPTED\',\'REJECTED\')),
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT uq_report_post_reporter UNIQUE (post_id, reporter_id),
                CONSTRAINT fk_report_post
                    FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
                CONSTRAINT fk_report_user
                    FOREIGN KEY (reporter_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_report_status  ON report (status)');
        $this->addSql('CREATE INDEX idx_report_post_id ON report (post_id)');

        // ─────────────────────────────────────────────
        // 18. NOTIFICATION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE notification (
                id         UUID        NOT NULL DEFAULT gen_random_uuid(),
                user_id    UUID        NOT NULL,
                type       VARCHAR(50) NOT NULL,
                content    TEXT        NOT NULL,
                is_read    BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_notification_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_notification_user_id ON notification (user_id)');
        $this->addSql('CREATE INDEX idx_notification_is_read ON notification (is_read)');

        // ─────────────────────────────────────────────
        // 19. POINT_TRANSACTION
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE point_transaction (
                id          UUID        NOT NULL DEFAULT gen_random_uuid(),
                user_id     UUID        NOT NULL,
                action_type VARCHAR(30) NOT NULL,
                amount      SMALLINT    NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_point_transaction_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE INDEX idx_point_transaction_user_id ON point_transaction (user_id)');

        // ─────────────────────────────────────────────
        // 20. REFRESH_TOKEN
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE refresh_token (
                id         UUID        NOT NULL DEFAULT gen_random_uuid(),
                user_id    UUID        NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                revoked_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_refresh_token_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX idx_refresh_token_hash    ON refresh_token (token_hash)');
        $this->addSql('CREATE INDEX        idx_refresh_token_user_id ON refresh_token (user_id)');

        // ─────────────────────────────────────────────
        // 21. FINE_TUNE_JOB
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE fine_tune_job (
                id               UUID     NOT NULL DEFAULT gen_random_uuid(),
                model_version_id UUID     NOT NULL,
                min_trust_score  SMALLINT NOT NULL,
                status           VARCHAR(15) NOT NULL DEFAULT \'PENDING\'
                                 CHECK (status IN (\'PENDING\',\'RUNNING\',\'COMPLETED\',\'FAILED\')),
                progress         SMALLINT NOT NULL DEFAULT 0
                                 CHECK (progress BETWEEN 0 AND 100),
                logs             TEXT,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_fine_tune_job_model
                    FOREIGN KEY (model_version_id) REFERENCES ai_model_version (id)
            )
        ');

        // ─────────────────────────────────────────────
        // 22. AUDIT_LOG (immuable — pas de UPDATE/DELETE)
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE audit_log (
                id          UUID        NOT NULL DEFAULT gen_random_uuid(),
                actor_id    UUID        NOT NULL,
                action      VARCHAR(50) NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                target_id   UUID        NOT NULL,
                reason      TEXT,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_audit_log_actor
                    FOREIGN KEY (actor_id) REFERENCES "user" (id) ON DELETE RESTRICT
            )
        ');
        $this->addSql('CREATE INDEX idx_audit_log_actor_id   ON audit_log (actor_id)');
        $this->addSql('CREATE INDEX idx_audit_log_created_at ON audit_log (created_at)');

        // Règle d'immuabilité : interdire UPDATE et DELETE sur audit_log
        $this->addSql('
            CREATE RULE audit_log_no_delete AS ON DELETE TO audit_log DO INSTEAD NOTHING
        ');
        $this->addSql('
            CREATE RULE audit_log_no_update AS ON UPDATE TO audit_log DO INSTEAD NOTHING
        ');

        // ─────────────────────────────────────────────
        // 23. GROUP_ENTITY
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE group_entity (
                id          UUID         NOT NULL DEFAULT gen_random_uuid(),
                owner_id    UUID         NOT NULL,
                name        VARCHAR(100) NOT NULL,
                description TEXT,
                visibility  VARCHAR(10)  NOT NULL DEFAULT \'public\'
                            CHECK (visibility IN (\'public\',\'private\')),
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_group_owner
                    FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 24. GROUP_MEMBER
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE group_member (
                group_id  UUID        NOT NULL,
                user_id   UUID        NOT NULL,
                role      VARCHAR(20) NOT NULL DEFAULT \'member\'
                          CHECK (role IN (\'owner\',\'admin\',\'member\')),
                joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (group_id, user_id),
                CONSTRAINT fk_group_member_group
                    FOREIGN KEY (group_id) REFERENCES group_entity (id) ON DELETE CASCADE,
                CONSTRAINT fk_group_member_user
                    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');

        // ─────────────────────────────────────────────
        // 25. INVOICE
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE invoice (
                id        UUID           NOT NULL DEFAULT gen_random_uuid(),
                client_id UUID           NOT NULL,
                amount    NUMERIC(10,2)  NOT NULL,
                status    VARCHAR(20)    NOT NULL DEFAULT \'PENDING\'
                          CHECK (status IN (\'PENDING\',\'PAID\',\'CANCELLED\')),
                issued_at TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
                paid_at   TIMESTAMPTZ,
                PRIMARY KEY (id),
                CONSTRAINT fk_invoice_client
                    FOREIGN KEY (client_id) REFERENCES client_profile (id) ON DELETE RESTRICT
            )
        ');

        // ─────────────────────────────────────────────
        // 26. FOLLOW
        // ─────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE follow (
                follower_id UUID        NOT NULL,
                followed_id UUID        NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (follower_id, followed_id),
                CONSTRAINT chk_follow_self CHECK (follower_id <> followed_id),
                CONSTRAINT fk_follow_follower
                    FOREIGN KEY (follower_id) REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT fk_follow_followed
                    FOREIGN KEY (followed_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        ');
    }

    public function down(Schema $schema): void
    {
        // Suppression dans l'ordre inverse pour respecter les FK
        $this->addSql('DROP TABLE IF EXISTS follow');
        $this->addSql('DROP TABLE IF EXISTS invoice');
        $this->addSql('DROP TABLE IF EXISTS group_member');
        $this->addSql('DROP TABLE IF EXISTS group_entity');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS fine_tune_job');
        $this->addSql('DROP TABLE IF EXISTS refresh_token');
        $this->addSql('DROP TABLE IF EXISTS point_transaction');
        $this->addSql('DROP TABLE IF EXISTS notification');
        $this->addSql('DROP TABLE IF EXISTS report');
        $this->addSql('DROP TABLE IF EXISTS user_badge');
        $this->addSql('DROP TABLE IF EXISTS badge');
        $this->addSql('DROP TABLE IF EXISTS vitrine_item');
        $this->addSql('DROP TABLE IF EXISTS vitrine');
        $this->addSql('DROP TABLE IF EXISTS validation');
        $this->addSql('DROP TABLE IF EXISTS embedding');
        $this->addSql('DROP TABLE IF EXISTS post_tag');
        $this->addSql('DROP TABLE IF EXISTS tag');
        $this->addSql('DROP TABLE IF EXISTS "like"');
        $this->addSql('DROP TABLE IF EXISTS comment');
        $this->addSql('DROP TABLE IF EXISTS post');
        $this->addSql('DROP TABLE IF EXISTS ai_model_version');
        $this->addSql('DROP TABLE IF EXISTS stone');
        $this->addSql('DROP TABLE IF EXISTS client_customer');
        $this->addSql('DROP TABLE IF EXISTS client_profile');
        $this->addSql('DROP TABLE IF EXISTS "user"');
        $this->addSql('DROP EXTENSION IF EXISTS "vector"');
        $this->addSql('DROP EXTENSION IF EXISTS "pgcrypto"');
    }
}