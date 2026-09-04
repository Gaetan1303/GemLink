# GemLink

GemLink est une application web dédiée aux pierres et minéraux.

Le projet permet de partager des spécimens, organiser des collections et échanger autour de leur identification. À terme, GemLink intégrera également un système de reconnaissance d'images afin d'aider à identifier les pierres publiées sur la plateforme.

## Sommaire

- [État du projet](#état-du-projet)
- [Fonctionnalités prévues](#fonctionnalités-prévues)
- [Architecture](#architecture)
- [Stack technique](#stack-technique)
- [Modèle métier](#modèle-métier)
- [Authentification](#authentification)
- [Installation de l'environnement de développement](#installation-de-lenvironnement-de-développement)
- [Tests](#tests)
- [Routes disponibles](#routes-disponibles)
- [Reconnaissance des pierres](#reconnaissance-des-pierres)
- [Structure du dépôt](#structure-du-dépôt)
- [Documentation](#documentation)
- [Maquettes](#maquettes)
- [CI/CD](#cicd)
- [Contributions](#contributions)
- [Contexte](#contexte)
- [Licences et production](#licences-et-production)

## État du projet

La branche `master` contient actuellement le socle technique du projet et une partie du domaine métier.

Déjà en place :

- backend Symfony 8.1 sous PHP 8.4 ;
- API Platform 4.3 ;
- authentification JWT ;
- inscription utilisateur ;
- hashage des mots de passe avec Argon2id ;
- envoi d'e-mails avec Symfony Mailer / Brevo ;
- PostgreSQL 16 avec pgvector ;
- Redis et Symfony Messenger ;
- modèle Doctrine et migrations ;
- premiers tests PHPUnit ;
- frontend Angular 21 ;
- Angular Material / CDK ;
- Docker ;
- CI GitHub Actions ;
- déploiement frontend prévu sur Cloudflare Pages ;
- Dockerfile backend basé sur FrankenPHP.

Le frontend, les parcours sociaux complets et le système de reconnaissance des pierres sont encore en cours de développement.

## Fonctionnalités prévues

GemLink doit permettre de :

- créer et gérer un compte utilisateur ;
- publier des pierres et minéraux avec leurs informations et leurs images ;
- commenter et aimer des publications ;
- créer des groupes ;
- gérer des collections et vitrines ;
- proposer ou valider une identification ;
- signaler et modérer du contenu ;
- gérer des badges, niveaux et scores de confiance ;
- rechercher des spécimens similaires ;
- utiliser un système de reconnaissance d'images pour assister l'identification.

## Architecture

```
Angular 21
    |
    | HTTP / JSON
    v
Symfony 8.1 / API Platform
    |
    +---- Redis
    |
    v
PostgreSQL 16 + pgvector
```

Le système d'analyse d'images sera intégré dans une étape ultérieure du projet.

## Stack technique

**Frontend**

- Angular 21
- TypeScript 5.9
- Angular Router
- Angular Material / CDK
- RxJS
- Vitest
- ESLint
- Prettier

**Backend**

- PHP 8.4+
- Symfony 8.1
- API Platform 4.3
- Doctrine ORM 3
- Symfony Security
- LexikJWTAuthenticationBundle
- Symfony Messenger
- Symfony Mailer
- PHPUnit 13
- FrankenPHP / Caddy

**Infrastructure**

- PostgreSQL 16
- pgvector
- Redis 7
- Docker
- GitHub Actions
- Cloudflare Pages
- Railway

## Modèle métier

Le dépôt contient notamment les entités suivantes :

User, Publication, Commentaire, Pierre, Validation, Vitrine, Groupe, Tag, Badge, Notification, Report, AuditLog, Embedding, VersionModeleIa, JobFineTuning, RefreshToken, PasswordResetToken, EmailValidationToken, Vendeur, Facture.

## Authentification

Le backend utilise JWT pour l'authentification de l'API.

Les mots de passe sont hashés avec Argon2id et plusieurs états de compte sont prévus :

```
PENDING_VALIDATION
ACTIVE
BANNED
```

Les rôles applicatifs prévus sont :

```
user
expert
moderator
client
admin
```

## Installation de l'environnement de développement

### Prérequis

- Git
- PHP 8.4+
- Composer
- Node.js 20+
- npm
- Docker et Docker Compose
- Symfony CLI
- Ollama

### Cloner le projet

```bash
git clone <URL_DU_DEPOT>
cd GemLink
```

### Backend

```bash
cd backend
composer install

composer require symfony/http-client && \
composer require league/flysystem-bundle
```

Démarrer PostgreSQL et Redis :

```bash
docker compose up -d database redis
```

Créer la configuration locale :

```bash
cp .env.example .env.local
```

Exemple de `.env.local` pour le développement :

```env
APP_ENV=dev
APP_SECRET=change-me-in-local
APP_SHARE_DIR=var/share
DEFAULT_URI=http://localhost

POSTGRES_VERSION=16
POSTGRES_DB= Pierre
POSTGRES_USER=Pierre
POSTGRES_PASSWORD=cestlagedepierre
DATABASE_URL="postgresql://Pierre:cestlagedepierre@127.0.0.1:5432/pierre?serverVersion=16&charset=utf8"

CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=

APP_API_ADMIN_LOGIN=
APP_API_ADMIN_PASSWORD_HASH=

REDIS_URL=redis://127.0.0.1:6380
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6380/messages
MESSENGER_FAILED_TRANSPORT_DSN=doctrine://default?queue_name=failed
```



Générer les clés JWT :

```bash
php bin/console lexik:jwt:generate-keypair
```

Préparer la base de données :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console doctrine:schema:validate
```

Installer le modèle Ollama utilisé en développement :

```bash
ollama pull gemma3:1b
```

Lancer le backend :

```bash
symfony server:start
```

Pour les traitements asynchrones avec Messenger :

```bash
php bin/console messenger:consume async -vv
```

### Frontend

Dans un autre terminal :

```bash
cd frontend
npm ci
npm start
```

## Tests

### Backend

```bash
cd backend
php bin/phpunit
php bin/console lint:container
php bin/console doctrine:schema:validate
```

### Frontend

```bash
cd frontend
npm test
npm run lint
npm run build
```

Formatage :

```bash
npm run format
```

## Routes disponibles

Quelques routes sont déjà présentes dans la branche :

| Méthode | Route | Description |
|---|---|---|
| POST | `/auth/register` | Inscription |
| POST | `/api/login_check` | Connexion JWT |
| GET | `/test/email` | Test d'envoi d'e-mail en développement |

Les autres routes métier seront ajoutées au fur et à mesure de l'implémentation des fonctionnalités.

## Reconnaissance des pierres

Le projet prévoit un système permettant d'analyser l'image d'un spécimen, de proposer une identification et de rechercher des pierres visuellement proches.

Le modèle de données prépare déjà cette partie avec les entités Embedding, Validation, VersionModeleIa et JobFineTuning, ainsi que l'utilisation de pgvector.

Cette partie n'est pas encore intégrée dans la branche master.

## Structure du dépôt

```
GemLink/
├── .github/
│   └── workflows/
├── backend/
│   ├── config/
│   ├── migrations/
│   ├── public/
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Message/
│   │   ├── MessageHandler/
│   │   ├── Repository/
│   │   ├── Service/
│   │   └── Validator/
│   ├── tests/
│   ├── compose.yaml
│   ├── Dockerfile
│   └── Caddyfile
├── frontend/
│   ├── public/
│   ├── src/
│   │   ├── app/
│   │   └── environments/
│   ├── angular.json
│   └── package.json
├── doc/
├── LICENSE
└── README.md
```

## Documentation

La documentation du projet est disponible dans `doc/`.

Principaux fichiers :

- `doc/cahier.md` : cahier des charges ;
- `doc/synopsis.md` : présentation du projet ;
- `doc/Entity.md` : documentation des entités ;
- `doc/MPD.md` : modèle physique de données ;
- `doc/Diagramme.md` : diagrammes ;
- `doc/migration.md` : migrations ;
- `doc/usecase/` : cas d'utilisation ;
- `doc/documentAfournir/` : documents liés au dossier DWWM.

## Maquettes

Maquette Figma :

https://www.figma.com/design/aUuzcybll0Xa1QnH8g92mW/GemLink?node-id=38-820&p=f&t=jih5HLkZO0blxZz5-0

Les captures des maquettes et wireframes sont également disponibles dans :

```
doc/documentAfournir/maquette/
doc/documentAfournir/wireframe/
```

## CI/CD

Le dépôt contient des workflows GitHub Actions pour le backend et le frontend.

Le backend exécute notamment l'installation Composer et la validation du conteneur Symfony.

Le frontend est construit avec `npm ci` et Angular, puis le workflow utilise Wrangler pour le déploiement Cloudflare Pages.

Les branches `master` et `staging` disposent de configurations distinctes dans les workflows actuels.

## Contributions

Les contributions au projet passent par Git et GitHub.

Avant de commencer une modification :

```bash
git checkout master
git pull
git checkout -b feature/nom-de-la-fonctionnalite
```

Une branche doit rester centrée sur une modification identifiable : fonctionnalité, correction, refactoring ou documentation.

Avant d'ouvrir une Pull Request, vérifier le backend :

```bash
cd backend
php bin/phpunit
php bin/console lint:container
php bin/console doctrine:schema:validate
```

Puis le frontend :

```bash
cd frontend
npm test
npm run lint
npm run build
```

Les changements de schéma Doctrine doivent être accompagnés de leur migration.

Les commits doivent rester lisibles et décrire clairement la modification apportée. Les Pull Requests permettent de relire les changements avant leur intégration dans master.

## Contexte

GemLink est développé dans le cadre de ma formation de développeur web et web mobile.

Le projet me sert à mettre en pratique la conception d'une application full-stack, la création d'une API, Angular, Symfony, PostgreSQL, Docker, les tests, la sécurité et le déploiement.

## Licences et production

Pour la production, GemLink vise l'utilisation de composants, bibliothèques, modèles et ressources dont les licences permettent explicitement leur utilisation et leur distribution dans le cadre du projet.

La priorité est donnée :

- aux licences libres compatibles avec une utilisation en production ;
- aux licences de type Apache 2.0 lorsque cela est possible ;
- aux ressources libres de droits ou disposant de conditions d'utilisation compatibles avec GemLink ;
- aux dépendances dont les obligations de licence peuvent être respectées et documentées.

Avant d'intégrer en production un modèle d'IA, un dataset, un asset ou une nouvelle dépendance, sa licence doit être vérifiée.

La licence du dépôt est disponible dans `LICENSE`.