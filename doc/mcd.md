MCD — StoneLink (version propre UML)

Légende
(1,1) = un seul
(0,n) = plusieurs possibles
(1,n) = obligatoire plusieurs

ENTITÉ USER
USER
-------------------------
id (PK)
username
email
password
avatar
created_at

Relations :
USER (1,1) → (0,n) POST
USER (1,1) → (0,n) COMMENT
USER (1,1) → (0,n) LIKE

ENTITÉ POST
POST
-------------------------
id (PK)
user_id (FK)
title
description
media_url
media_type (image/video)
created_at

Relations :
POST (1,1) → (0,n) COMMENT
POST (1,1) → (0,n) LIKE
POST (1,1) → (0,1) EMBEDDING
POST (0,n) ↔ (0,n) TAG
POST (0,n) → (0,1) CLUSTER
POST (0,1) → (0,1) STONE

ENTITÉ COMMENT
COMMENT
-------------------------
id (PK)
post_id (FK)
user_id (FK)
content
created_at

Relations :
COMMENT appartient à POST
COMMENT appartient à USER

ENTITÉ LIKE
LIKE
-------------------------
id (PK)
post_id (FK)
user_id (FK)
created_at

Relations :
USER (1,n) LIKE
POST (1,n) LIKE

ENTITÉ TAG
TAG
-------------------------
id (PK)
name

Relation N:N avec POST

ENTITÉ STONE (catalogue officiel)
STONE
-------------------------
id (PK)
name
category
hardness
composition
description

Relations :
STONE (1,n) → POST (optionnel)
plusieurs posts peuvent référencer une pierre

ENTITÉ EMBEDDING (IA)
EMBEDDING
-------------------------
id (PK)
post_id (FK)
vector (JSON / vector DB)
created_at

Relations :
POST (1,1) → (0,1) EMBEDDING

ENTITÉ CLUSTER
CLUSTER
-------------------------
id (PK)
name
centroid_vector

Relations :
CLUSTER (1,1) → (0,n) POST

RELATIONS GLOBAL VIEW (schéma logique)
USER
 │
 │ 1,n
 ▼
POST ──────────────── TAG (N:N)
 │  │
 │  │
 │  ├── COMMENT (1,n)
 │  ├── LIKE (1,n)
 │  ├── EMBEDDING (0,1)
 │  ├── CLUSTER (0,1)
 │  └── STONE (0,1)


