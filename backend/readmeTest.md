# Tests — Backend GemLink

Ce document explique comment configurer et lancer l'environnement de tests (PHPUnit) du backend Symfony, en local, avec une base PostgreSQL de test isolée.

## Pourquoi une base de test séparée ?

L'environnement de test doit être **isolé** de la base de développement pour éviter :
- d'écraser des données de dev pendant les tests ;
- des résultats de tests instables (état résiduel entre deux runs) ;
- des conflits de port ou d'authentification avec le conteneur Postgres principal.

On utilise donc un second conteneur Postgres, dédié aux tests, avec un stockage **en mémoire (`tmpfs`)** : la base est entièrement réinitialisée à chaque redémarrage du conteneur.

## Prérequis

- Docker et Docker Compose installés
- PHP 8.4 et Composer installés en local (hors conteneur, pour lancer `bin/console` et `bin/phpunit`)
- Le conteneur `gemlink_php` du `compose.yaml` principal doit être disponible pour générer des migrations si besoin

## 1. Lancer la base de test

Un fichier dédié `compose.test.yaml` définit le conteneur Postgres de test sur un **port différent** (`5433`) du conteneur de développement (`5432`), pour éviter tout conflit :

```bash
docker compose -f compose.test.yaml up -d
```

Vérifier que le conteneur tourne :

```bash
docker ps | grep gemlink_postgres_test
```

## 2. Configurer l'environnement de test

Créer (ou vérifier) le fichier **`.env.test.local`** à la racine de `backend/` (ce fichier est ignoré par Git, il ne doit jamais être commité) :

```env
DATABASE_URL="postgresql://gemlink:gemlink-test-only-password@127.0.0.1:5433/gemlink?serverVersion=16&charset=utf8"
```

> ⚠️ Ne pas ajouter `_test` au nom de la base dans l'URL : Doctrine l'ajoute automatiquement quand la commande est lancée avec `--env=test`.

Le fichier **`phpunit.dist.xml`** doit définir la classe du kernel :

```xml
<php>
    <server name="APP_ENV" value="test" force="true" />
    <server name="KERNEL_CLASS" value="App\Kernel" />
</php>
```

## 3. Créer la base et appliquer les migrations

Depuis l'hôte (pas depuis le conteneur `gemlink_php`, qui pointe vers la base de dev) :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

Vérifier que les tables sont bien créées :

```bash
docker exec -it gemlink_postgres_test psql -U gemlink -d gemlink_test -c '\dt'
```

## 4. Lancer les tests

Tous les tests :

```bash
php bin/phpunit
```

Un seul fichier :

```bash
php bin/phpunit tests/
```

## 5. Arrêter / réinitialiser la base de test

Comme la base utilise un `tmpfs`, **toutes les données sont perdues à l'arrêt du conteneur** — c'est volontaire, ça garantit un environnement propre à chaque session de test.

```bash
docker compose -f compose.test.yaml down
```

Pour repartir de zéro lors d'une prochaine session, il suffit de relancer les étapes 1 et 3 :

```bash
docker compose -f compose.test.yaml up -d
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

## Ajouter une nouvelle migration

Quand une nouvelle entité ou un changement de schéma est nécessaire :

```bash
docker exec -it gemlink_php php bin/console make:migration
```

⚠️ **Toujours relire le contenu généré avant de l'exécuter.** `make:migration` compare *toutes* les entités au schéma réel : si le schéma de dev a dérivé (modifications manuelles, migrations partiellement appliquées...), la migration générée peut contenir des `DROP TABLE` ou des changements non voulus. En cas de doute, écrire la migration manuellement plutôt que de lancer le fichier auto-généré tel quel.

Une fois la migration validée, l'appliquer sur les deux environnements :

```bash
# Dev
docker exec -it gemlink_php php bin/console doctrine:migrations:migrate --no-interaction

# Test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

## Dépannage rapide

| Symptôme | Cause probable | Solution |
|---|---|---|
| `KERNEL_CLASS environment variable` | Variable absente de `phpunit.dist.xml` | Ajouter `<server name="KERNEL_CLASS" value="App\Kernel" />` |
| `could not translate host name "gemlink_postgres"` | Commande lancée depuis l'hôte avec un `DATABASE_URL` pointant vers le nom du service Docker | Utiliser `127.0.0.1` au lieu du nom du conteneur depuis l'hôte |
| `authentification par mot de passe échouée` | Volume Postgres déjà initialisé avec d'anciens identifiants | Supprimer le volume concerné et relancer le conteneur |
| `database "gemlink_test_test" already exists` ou tables manquantes | Doctrine ajoute automatiquement `_test` en mode test | Ne pas mettre `_test` dans le nom de base de `DATABASE_URL` |
| `Booting the kernel before calling createClient()` | `createClient()` appelé plusieurs fois (ex: une fois en `setUp()`, une fois par test) | Stocker le client dans une propriété de classe et le réutiliser |
| Table manquante après migration | Migration non détectée par Doctrine | Vider le cache (`php bin/console cache:clear --env=test`) et vérifier que le fichier de migration est bien présent côté hôte |
