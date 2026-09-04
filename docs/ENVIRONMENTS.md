# Environment inventory

Blank secret values are intentional. Store Railway values as sealed variables
and Cloudflare credentials as GitHub environment secrets. Angular environment
files are public bundle inputs and contain no secrets.

| Variable | Dev | Railway | Cloudflare | Secret? |
|---|---|---|---|---|
| `APP_ENV` (Symfony) | `dev` | `prod` | — | No |
| `APP_DEBUG` | `1` | `0` | — | No |
| `APP_SECRET` | local-only random value | shared API/worker sealed value | — | Yes |
| `ADMIN_FIXTURE_PASSWORD` | required only for explicit fixture loading | absent | — | Yes |
| `APP_URL` / `DEFAULT_URI` | `http://localhost:8000` | public API HTTPS URL | — | No |
| `FRONTEND_URL` | `http://localhost:4200` | `https://gem-link.org` | — | No |
| `TRUSTED_PROXIES` | loopback/`REMOTE_ADDR` | Railway proxy range validated by owner | — | No |
| `TRUSTED_HOSTS` | localhost regex | API host regex | — | No |
| `CORS_ALLOW_ORIGIN` | anchored localhost regex | `^https://(www\\.)?gem-link\\.org$` | — | No |
| `DATABASE_URL` | local pg16 DSN | PostgreSQL reference variable | — | Yes |
| `POSTGRES_VERSION` | `16` | PostgreSQL service 16 | — | No |
| `POSTGRES_DB` / `POSTGRES_USER` | local values | PostgreSQL service variables | — | No |
| `POSTGRES_PASSWORD` | local dev value | sealed PostgreSQL value | — | Yes |
| `REDIS_URL` | local Redis 7.2 | Redis private reference | — | Possibly |
| `MESSENGER_TRANSPORT_DSN` | complete `/messages` DSN | complete private DSN | — | Possibly |
| `MESSENGER_LOW_PRIORITY_TRANSPORT_DSN` | complete low-priority DSN | complete private DSN | — | Possibly |
| `MESSENGER_FAILED_TRANSPORT_DSN` | Doctrine failed queue | same for API/worker | — | No |
| `MAILER_DSN` | Mailpit or null | production provider DSN | — | Yes |
| `MAILER_FROM_EMAIL` / `MAILER_FROM_NAME` | test identity | verified sender | — | No |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` | local ignored PEM paths | mounted secret/volume paths | — | Private key file is secret |
| `JWT_PASSPHRASE` | local-only | sealed shared value | — | Yes |
| `AI_ENABLED` | `true` | explicit boolean | — | No |
| `AI_SERVICE_URL` | `http://ai:3000` | `http://${{gemlink-ai.RAILWAY_PRIVATE_DOMAIN}}:3000` | Never | No |
| `INTERNAL_API_KEY` | local 32+ chars | same sealed value on API/worker/AI | Never | Yes |
| `MEDIA_STORAGE_MODE` | `local` | `r2` | — | No |
| `R2_ACCOUNT_ID` | blank | API/worker metadata | account workflow secret if needed | No |
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | blank | sealed API/worker values | Never in Angular | Yes |
| `R2_BUCKET` / `R2_ENDPOINT` / `R2_PUBLIC_BASE_URL` | blank | API/worker values | public base URL may be used by browser responses | Secret only for credentials |
| `INFOMANIAK_NEWSLETTER_TOKEN` | blank | sealed API/worker value | — | Yes |
| `INFOMANIAK_NEWSLETTER_CLIENT_API_URL` | official endpoint | official endpoint | — | No |
| `INFOMANIAK_NEWSLETTER_DOMAIN_ID` | test value | account domain ID | — | No |
| `APP_ENV` (FastAPI) | `development` | `production` | Never | No |
| `PORT` | `3000` | Railway-provided | Never | No |
| `AI_ENABLED` / `VISION_ENABLED` / `LLM_ENABLED` | explicit booleans | explicit booleans | Never | No |
| `OLLAMA_URL` | private Compose hostname | `http://${{gemlink-ollama.RAILWAY_PRIVATE_DOMAIN}}:11434` | Never | No |
| `OLLAMA_TEXT_MODEL` | `qwen3:0.6b` | `qwen3:0.6b` explicitly | Never | No |
| `OLLAMA_VISION_MODEL` | `moondream` | explicitly approved tag | Never | No |
| `DETECTOR_BACKEND` | `torchvision` | `torchvision` | Never | No |
| `DETECTOR_MODEL_PATH` / `DETECTOR_MODEL_VERSION` | mounted checkpoint/version | persistent volume path/version | Never | No |
| `DETECTION_CONFIDENCE_THRESHOLD` | `0.5` | reviewed numeric value | Never | No |
| `VIT_MODEL_PATH` / `VIT_MODEL_VERSION` | mounted checkpoint/version | persistent volume path/version | Never | No |
| `VIT_VERSIONS_ROOT` / `FINE_TUNE_STATE_PATH` | local checkpoints | persistent AI volume | Never | No |
| `FINE_TUNE_MAX_LOG_LINES` / `FINE_TUNE_EPOCHS` | documented defaults | explicit operational values | Never | No |
| `CLIP_MODEL_ARCH` / `CLIP_MODEL_PATH` / `CLIP_MODEL_PRETRAINED` / `CLIP_MODEL_VERSION` | local 512D contract; no implicit download | mounted, explicitly approved artifact | Never | Path: no; approval: yes |
| `MEDIA_INTERNAL_BASE_URL` | private API hostname | private API hostname | Never | No |
| `CLOUDFLARE_API_TOKEN` | absent | — | GitHub production environment | Yes |
| `CLOUDFLARE_ACCOUNT_ID` | absent | — | GitHub production environment | Sensitive identifier |

Training-only variables (`DATASET_ROOT`, `FINE_TUNE_OUTPUT_PATH`,
`FINE_TUNE_BASE_MODEL_PATH`, `FINE_TUNE_MODEL_VERSION`) belong to an offline
training job, not the production runtime. `TEST_TOKEN` is generated only by
parallel Doctrine test runs.
