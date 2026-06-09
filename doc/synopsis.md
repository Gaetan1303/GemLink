# Présentation du projet GemLink

GemLink est une application web full-stack spécialisée dans l’identification et le partage de pierres et minéraux. Le projet combine les fonctionnalités d’un réseau social communautaire avec un système de reconnaissance visuelle assisté par intelligence artificielle.

L’objectif principal est de permettre à un utilisateur de photographier une pierre, d’obtenir une proposition d’identification automatique, puis de partager sa découverte avec une communauté de passionnés et d’experts. Les validations réalisées par la communauté alimentent progressivement l’amélioration du système de reconnaissance grâce à un mécanisme d’apprentissage continu.

## Fonctionnalités

La plateforme propose plusieurs fonctionnalités : 
- Un Espace Membre avec une création et gestion de compte utilisateur
- Publication de photos et vidéos
- Système de commentaires et de likes
- Validation communautaire des identifications
- Gestion de collections personnalisées appelées « vitrines »
- Attribution de badges et de points
- Système de modération et d’administration

## Architecture
Le projet repose sur une architecture modulaire composée d’un frontend développé avec : 
- Angular pour une interface utilisateur réactive et moderne.
- Une API REST réalisée avec Symfony 
- Une base de données PostgreSQL. 
- Redis est utilisé pour la mise en cache et la gestion des traitements asynchrones via Symfony Messenger. 
- Les médias sont stockés sur un service CDN externe afin d’optimiser les performances de chargement.

## A propos de l’intelligence artificielle

L’intelligence artificielle est isolée dans un service FastAPI développé en Python. Ce service analyse les images grâce à un pipeline composé de modèles YOLO, ViT et CLIP afin de détecter les pierres, proposer une identification et générer des embeddings vectoriels stockés dans PostgreSQL via l’extension pgvector.

## A propos de la sécurité de l'application

La sécurité de l’application repose sur : 
- l’utilisation de JWT pour l’authentification, 
- du chiffrement des mots de passe avec Argon2id, 
- d’un système de rôles et permissions (RBAC), 
- de la validation des données côté serveur 
- et d’une journalisation des actions sensibles via un audit log.

## Objectif du projet

GemLink a été conçu comme une plateforme moderne, évolutive et performante permettant de mettre en relation passionnés, collectionneurs et experts autour d’un outil d’identification assisté par intelligence artificielle.
