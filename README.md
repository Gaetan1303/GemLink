# GemmeLink / StoneLink

## Projet de fin d'année — CDPI / RNCP31114

GemLink est un projet de plateforme web communautaire dédié aux passionnés de pierres, minéraux et cristaux. Il réunit un réseau social spécialisé, la gestion de médias (images/vidéos) et une reconnaissance visuelle assistée par intelligence artificielle.

## Objectif

L'objectif du projet est de proposer une application où chaque utilisateur peut :
- publier des photos et courtes vidéos de pierres,
- partager ses découvertes,
- interagir avec la communauté,
- bénéficier d'une identification automatique et d'une recherche de pierres similaires,
- créer et partager des collections de pierres.

## Fonctionnalités principales

- Authentification sécurisée et gestion de profil
- Publication de posts avec images et vidéos
- Feed communautaire avec likes, commentaires et suivi
- Analyse IA asynchrone des images de pierres
- Recherche de similarité visuelle entre publications
- Système de tags et catégories
- Collections de pierres publiques (setups)
- Gamification : points, niveaux, badges, Trust Score
- Modération et administration des contenus

## Contexte métier

Ce projet est réalisé dans le cadre du titre professionnel RNCP31114 — Développeur Web et Web Mobile. Il vise à démontrer :
- une architecture web moderne,
- une API sécurisée,
- le traitement de médias,
- l'intégration d'une chaîne IA,
- la gestion de données métiers et de performances.

## Stack technique proposée

- Frontend : Angular
- Backend : Symfony
- Base de données : PostgreSQL (+ pgvector)
- Stockage médias : Cloudinary / solution CDN
- Cache et files d'attente : Redis
- Service IA : pipeline de classification et embeddings

## Architecture générale

1. Frontend Angular
2. API REST Symfony
3. Base de données PostgreSQL
4. Stockage externes des médias
5. Service IA asynchrone pour analyse et similarité

## Documentation

Les documents du projet sont disponibles dans le dossier `doc/` :
- `doc/cahierdescharges.md`
- `doc/technique.md`
- `doc/resume.md`
- `doc/domainemetier.md`
- `doc/roadmap.md`
- `doc/SQL.md`
- `doc/bdd.md`

