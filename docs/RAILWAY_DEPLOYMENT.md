# Railway production deployment

Railway is not deployed from `compose.yaml`. Create six services in one
production environment so private DNS remains environment-scoped.

## Services

1. `postgres`: PostgreSQL 16 with pgvector enabled and a persistent volume.
2. `redis`: image `redis:7.2-alpine`, persistent `/data` volume, no public
   domain. Do not upgrade to Redis 7.4+ without a new license review.
3. `gemlink-ollama`: pinned Ollama image, persistent `/root/.ollama` volume,
   port 11434, no public domain.
4. `gemlink-ai`: repository root `Fastapi`, `Fastapi/Dockerfile`, port 3000,
   persistent `/app/checkpoints` volume, no public domain, HTTP health path
   `/health`.
5. `gemlink-api`: repository root `backend`, `backend/Dockerfile`, port 8080,
   the only Railway public domain, HTTP health path `/health`.
6. `gemlink-worker`: same root/image/variables as the API, no public domain,
   start command `php bin/console messenger:consume async low_priority
   --time-limit=3600 --memory-limit=256M --sleep=1`.

Use Railway reference variables instead of Docker hostnames:

```text
AI_SERVICE_URL=http://${{gemlink-ai.RAILWAY_PRIVATE_DOMAIN}}:3000
OLLAMA_URL=http://${{gemlink-ollama.RAILWAY_PRIVATE_DOMAIN}}:11434
DATABASE_URL=${{postgres.DATABASE_URL}}
REDIS_URL=${{redis.REDIS_URL}}
```

Define complete Messenger DSNs explicitly from the Redis private connection;
do not concatenate a path onto an already path-qualified provider URL. API and
worker must receive identical database, Redis, mail, JWT, AI, R2 and Infomaniak
variables. Set `APP_ENV=prod`, `APP_DEBUG=0`, `MEDIA_STORAGE_MODE=r2` on both.

## Secret handling

Generate independent high-entropy `APP_SECRET`, `JWT_PASSPHRASE` and a 32+
character `INTERNAL_API_KEY`. Share the internal key only across API, worker
and AI. Seal database credentials, mail DSN, JWT private material, internal
key, R2 credentials and Infomaniak token in Railway. Never paste them into a
Dockerfile, Angular file, build argument or repository `.env.production`.

Store JWT keys using a Railway volume or secret-file mechanism and point
`JWT_SECRET_KEY`/`JWT_PUBLIC_KEY` to those files. Run migrations as a release
step before switching traffic:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Never run `doctrine:schema:update --force`.

## Model initialization

Attach the Ollama volume first. In a one-off shell run:

```bash
ollama pull qwen3:0.6b
ollama pull moondream
ollama list
```

The persistent volume prevents downloads on normal redeploys. Install only
model tags approved in `PRODUCTION_LICENSES.md`. Upload the provenance-approved
`detector_stones.pth` and `vit_stones.pth` to the AI checkpoint volume. Until
then `/health` is `degraded` and inference returns `DETECTOR_NOT_READY` while
Symfony remains online.

## Network and acceptance checks

Delete/avoid generated public domains for worker, AI, Ollama, PostgreSQL and
Redis. From the API/worker shell, verify private `/health` and Ollama
`/api/tags`. From the public internet, their ports must be unreachable.

After deployment verify:

```text
GET  https://<api-domain>/health
OPTIONS /api/publications with Origin https://gem-link.org
authenticated upload -> R2 public URL
Messenger async and low_priority consumption
failed messages visible through messenger:failed:show
Symfony -> FastAPI with X-Internal-Key
FastAPI -> Ollama on private DNS
```

The API health endpoint intentionally returns HTTP 200 with `degraded` when AI
is unavailable, preventing an optional model outage from killing the web API.
Database or Redis degradation remains visible in the component map.
