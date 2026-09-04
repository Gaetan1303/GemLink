# Provisionnement Railway production

Ce dossier permet de créer/configurer l'infrastructure GemLink sans remplir les variables service par service dans l'interface Railway.

Le script est idempotent pour les services applicatifs : il réutilise les services existants et crée les services manquants. Il ne supprime jamais une base ou un volume existant.

## Services

- `backend` : API Symfony/FrankenPHP, seule cible publique.
- `worker` : **worker PHP Symfony Messenger**, sans serveur HTTP.
- `pgvector` : PostgreSQL 16 + pgvector.
- `Redis` : Redis 7.2 Alpine, épinglé à la dernière branche BSD-3-Clause retenue par le projet.
- `FastAPI` : IA privée.
- `Ollama` : runtime LLM privé avec volume persistant.

## Utilisation

```bash
cp deploy/railway/.env.production.example deploy/railway/.env.production
$EDITOR deploy/railway/.env.production

railway login
railway link

./deploy/railway/provision-prod.sh
```

Les secrets internes sont générés dans `deploy/railway/.generated-secrets.env`. Ce fichier est local et ne doit jamais être versionné.

## Worker PHP

Railway exécute :

```bash
/app/bin/railway-worker-start
```

qui prépare les clés JWT puis lance uniquement :

```bash
php bin/console messenger:consume async low_priority \
  --time-limit=3600 \
  --memory-limit=256M \
  --sleep=1 \
  --no-interaction
```

Le worker reprend les variables du backend via des références Railway et utilise les mêmes PostgreSQL, Redis, R2 et FastAPI.

## Secrets externes

Le script ne peut pas inventer des identifiants Cloudflare R2 ou un token de mailer. Ils sont renseignés une seule fois dans `.env.production`, puis poussés automatiquement vers Railway.

Les anciennes clés exposées auparavant doivent rester révoquées.
