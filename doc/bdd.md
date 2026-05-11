1. SQL — coeur métier (PostgreSQL)

PostgreSQL

Cette couche contient toute la logique du réseau social et de la structure des données.

USERS
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

POSTS
CREATE TABLE posts (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(150),
    description TEXT,
    media_url TEXT,
    media_type VARCHAR(20), -- image / video
    created_at TIMESTAMP DEFAULT NOW()
);

COMMENTS
CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    post_id INT REFERENCES posts(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    content TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

LIKES
CREATE TABLE likes (
    id SERIAL PRIMARY KEY,
    post_id INT REFERENCES posts(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(post_id, user_id)
);

TAGS
CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

POST_TAGS (N:N)
CREATE TABLE post_tags (
    post_id INT REFERENCES posts(id) ON DELETE CASCADE,
    tag_id INT REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (post_id, tag_id)
);

STONES (catalogue scientifique)
CREATE TABLE stones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    category VARCHAR(100),
    hardness VARCHAR(50),
    composition TEXT,
    description TEXT
);

CLUSTERS (groupes IA)
CREATE TABLE clusters (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    centroid_vector JSONB
);

POSTS ↔ CLUSTERS
ALTER TABLE posts
ADD COLUMN cluster_id INT REFERENCES clusters(id);

2. NoSQL — couche IA et flexibilité

NoSQL / vector store (MongoDB ou équivalent) permet de stocker des données dynamiques et peu structurées liées à l’IA.

Option 1 — embeddings (IA)
{
  "post_id": 123,
  "vector": [0.12, -0.98, 0.44, 0.33],
  "model": "clip-vit",
  "created_at": "2026-05-11"
}

Pourquoi NoSQL ici ?
- taille variable
- évolution du modèle IA
- stockage flexible

Option 2 — cache de similarité
{
  "post_id": 123,
  "similar_posts": [
    { "id": 45, "score": 0.91 },
    { "id": 78, "score": 0.88 }
  ],
  "updated_at": "2026-05-11"
}

Option 3 — logs IA
{
  "post_id": 123,
  "input_image": "url",
  "predictions": [
    "quartz",
    "amethyst",
    "calcite"
  ],
  "confidence": [0.82, 0.14, 0.04]
}

Option 4 — clusters dynamiques
{
  "cluster_id": 1,
  "name": "Quartz family",
  "center": [0.1, 0.9, -0.2],
  "posts": [12, 44, 98, 101]
}

3. Architecture hybride SQL + NoSQL

Angular (UI)
      ↓
Symfony API (business logic)
      ↓
PostgreSQL (users, posts, social graph)
      ↓
NoSQL / Vector DB (IA + embeddings)

Rôle de chaque couche

SQL (Symfony / Doctrine)
- users
- posts
- comments
- likes
- tags
- relations sociales

NoSQL (couche IA)
- embeddings
- similarité
- clusters dynamiques
- logs IA
- recommandations

Avantages
- réaliste pour l’industrie
- scalable
- claire pour un jury
- séparation des responsabilités
- performance optimisée

Pièges à éviter
- ne pas stocker tout en NoSQL
- ne pas mélanger la logique IA et Symfony
- ne pas dupliquer les données inutilement
- ne pas recalculer les embeddings à chaque requête

Version simplifiée (MVP conseillé)
Phase 1 : uniquement SQL (Symfony)
Phase 2 : ajout d’embeddings NoSQL
Phase 3 : similarité + clustering