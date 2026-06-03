# StoneLink — Réseau Social de Passionnés de Minéraux & Reconnaissance Visuelle Assistée par IA

## Présentation du projet

StoneLink est une plateforme communautaire dédiée aux passionnés de pierres, minéraux et cristaux.

L’application permet aux utilisateurs de :
- publier des photos et des courtes vidéos de pierres ;
- partager leurs découvertes ;
- interagir avec la communauté ;
- retrouver des pierres similaires grâce à un système de reconnaissance visuelle assisté par IA.

Le projet est réalisé dans le cadre du titre professionnel RNCP31114 — Développeur Web et Web Mobile.

## Concept

Le projet combine :
- un réseau social spécialisé ;
- un système de médias (images et vidéos courtes) ;
- une recherche intelligente basée sur la similarité visuelle.

Plutôt que de se concentrer sur une reconnaissance stricte, le système exploite :
- les contenus publiés par la communauté ;
- les métadonnées ;
- la comparaison d’images similaires.

L’intelligence du projet repose autant sur les utilisateurs que sur l’IA.

## Objectifs fonctionnels

- création de compte utilisateur
- authentification sécurisée
- publication de photos et de vidéos courtes
- feed communautaire
- likes et commentaires
- recherche de pierres similaires
- historique des publications
- système de tags et catégories
- profils utilisateurs
- responsive mobile

## Fonctionnalités principales

### Réseau social communautaire

Les utilisateurs peuvent :
- publier leurs découvertes ;
- suivre d’autres utilisateurs ;
- commenter ;
- liker ;
- sauvegarder des publications.

### Upload de médias

Chaque publication peut contenir :
- une ou plusieurs images ;
- une courte vidéo.

Les médias sont compressés et optimisés avant stockage.

### Reconnaissance visuelle assistée

Lorsqu’un utilisateur publie une image :
- le système génère un embedding visuel ;
- il recherche des images similaires déjà présentes dans la base ;
- il propose plusieurs correspondances.

Le système fonctionne comme une recherche par similarité d’image.

### Fiche pierre / minéral

Chaque pierre peut contenir :
- nom ;
- catégorie ;
- description ;
- dureté Mohs ;
- composition ;
- origine ;
- photos et vidéos associées.

### Système de tags

Exemples :
- Quartz
- Améthyste
- Obsidienne
- Cristal
- Pierre brute
- Minéral rare

## Objectifs techniques

Le projet permet de démontrer :
- développement front-end moderne ;
- architecture back-end sécurisée ;
- gestion d’API REST ;
- gestion de médias ;
- stockage cloud ;
- base de données relationnelle ;
- intégration IA ;
- recherche visuelle ;
- optimisation des performances ;
- sécurité web ;
- déploiement d’une application complète.

## Stack technique

- Front-end : Angular
- Back-end : Symfony
- Base de données : PostgreSQL
- Stockage médias : Cloudinary
- IA : CLIP embeddings, similarité vectorielle
- Recherche vectorielle : PostgreSQL + pgvector
- Déploiement : hébergement cloud / service web

## Architecture du projet

Frontend Angular
        ↓
API REST Symfony
        ↓
PostgreSQL
        ↓
Cloudinary (médias)
        ↓
Service IA Similarité Visuelle


## Problématiques rencontrées

