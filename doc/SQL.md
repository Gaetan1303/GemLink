1. USERS (base du système)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar TEXT,
    status VARCHAR(20) DEFAULT 'active', -- active / banned
    created_at TIMESTAMP DEFAULT NOW()
);

2. ROLES (RBAC simple)
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

USER_ROLES (N:N)
CREATE TABLE user_roles (
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    role_id INT REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

Rôles initiaux
ROLE_USER
ROLE_MODERATOR
ROLE_ADMIN

3. USER_STATS (coeur de la gamification)
CREATE TABLE user_stats (
    user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    points INT DEFAULT 0,
    level INT DEFAULT 1,
    uploads_count INT DEFAULT 0,
    likes_received INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    validations_count INT DEFAULT 0,
    trust_score NUMERIC(5,2) DEFAULT 0.0,
    updated_at TIMESTAMP DEFAULT NOW()
);

Table centrale pour le suivi de progression.

4. BADGES (système de récompense)
CREATE TABLE badges (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon TEXT,
    type VARCHAR(50) -- upload / engagement / expertise / moderation
);

USER_BADGES (N:N)
CREATE TABLE user_badges (
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    badge_id INT REFERENCES badges(id) ON DELETE CASCADE,
    earned_at TIMESTAMP DEFAULT NOW(),
    PRIMARY KEY (user_id, badge_id)
);

5. POINT TRANSACTIONS (trace des points)
CREATE TABLE points_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    points INT NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW()
);

Permet de tracer l’évolution des points.

6. MODÉRATION
CREATE TABLE moderation_actions (
    id SERIAL PRIMARY KEY,
    moderator_id INT REFERENCES users(id),
    target_user_id INT REFERENCES users(id),
    action_type VARCHAR(50), -- warn / ban / validate / reject
    reason TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

7. TRUST SYSTEM (IA + communauté)
CREATE TABLE user_trust (
    user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    trust_score NUMERIC(5,2) DEFAULT 0.0,
    correct_validations INT DEFAULT 0,
    incorrect_validations INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT NOW()
);

8. LEVEL SYSTEM (option simplifiée)
CREATE TABLE user_level (
    user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    level INT DEFAULT 1,
    xp INT DEFAULT 0
);

Ce système peut être calculé dynamiquement, mais une version stockée est plus simple.

9. RÈGLES DE GAMIFICATION
Points
Action	Points
upload post	+10
like reçu	+2
commentaire	+1
validation correcte	+20
correction d’erreur IA	+30

Leveling
level = sqrt(xp / 100)

ou seuils simples :
0 → niveau 1
100 → niveau 2
300 → niveau 3
700 → niveau 4

Trust score
trust = correct_validations / total_validations

10. SCHÉMA GLOBAL
USERS
 ├── USER_ROLES
 ├── USER_STATS
 ├── USER_LEVEL
 ├── USER_TRUST
 ├── USER_BADGES
 ├── POINTS_TRANSACTIONS
 └── MODERATION_ACTIONS

11. Intégration dans le projet
Angular
- affiche badges
- affiche niveau utilisateur
- affiche progression
- affiche leaderboard

Symfony
- calcule les points
- met à jour les stats
- gère les rôles
- applique le trust score

IA
- utilise le trust_score pour pondérer les validations

Redis (optionnel)
- leaderboard temps réel
- cache des stats utilisateur
- feed ranking

12. Ce que ça rend possible
- gamification forte
- engagement naturel
- auto-modération
- communauté fiable
- IA améliorée
- rétention utilisateur renforcée

Résumé simple
1. USERS (identité)
2. ROLES (permissions)
3. STATS (progression)
4. BADGES (récompenses)
5. TRUST (influence IA)


