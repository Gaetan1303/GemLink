#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
CONFIG_FILE="${GEMLINK_RAILWAY_ENV_FILE:-$SCRIPT_DIR/.env.production}"
SECRETS_FILE="$SCRIPT_DIR/.generated-secrets.env"

log() { printf '\n[GemLink Railway] %s\n' "$*"; }
die() { printf '\n[GemLink Railway] ERREUR: %s\n' "$*" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || die "commande requise absente: $1"; }

need railway
need openssl
need base64

[ -f "$CONFIG_FILE" ] || die "copie $SCRIPT_DIR/.env.production.example vers $CONFIG_FILE puis renseigne les valeurs externes."
set -a
# shellcheck disable=SC1090
. "$CONFIG_FILE"
set +a

RAILWAY_ENVIRONMENT="${RAILWAY_ENVIRONMENT:-prod}"
GITHUB_BRANCH="${GITHUB_BRANCH:-dev}"
BACKEND_DOMAIN="${BACKEND_DOMAIN:-caverne.gem-link.org}"
FRONTEND_URL="${FRONTEND_URL:-https://gem-link.org}"
CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-^https://(www\\.)?gem-link\\.org$}"
RESET_REDIS="${RESET_REDIS:-0}"

[ -n "${GITHUB_REPO:-}" ] || die "GITHUB_REPO est obligatoire (ex: owner/GemLink)."
for var in MAILER_DSN R2_ACCOUNT_ID R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_BUCKET R2_ENDPOINT R2_PUBLIC_BASE_URL; do
  [ -n "${!var:-}" ] || die "$var est obligatoire pour APP_ENV=prod avec la configuration actuelle."
done

if [ -f "$SECRETS_FILE" ]; then
  set -a
  # shellcheck disable=SC1090
  . "$SECRETS_FILE"
  set +a
else
  log "Génération des secrets internes GemLink"
  APP_SECRET="$(openssl rand -hex 32)"
  INTERNAL_API_KEY="$(openssl rand -hex 32)"
  JWT_PASSPHRASE="$(openssl rand -hex 24)"
  REDIS_PASSWORD="$(openssl rand -hex 32)"
  NEW_POSTGRES_PASSWORD="$(openssl rand -hex 32)"

  tmpdir="$(mktemp -d)"
  trap 'rm -rf "$tmpdir"' EXIT
  openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 \
    -aes-256-cbc -pass "pass:$JWT_PASSPHRASE" -out "$tmpdir/private.pem" >/dev/null 2>&1
  openssl pkey -in "$tmpdir/private.pem" -passin "pass:$JWT_PASSPHRASE" \
    -pubout -out "$tmpdir/public.pem" >/dev/null 2>&1
  JWT_PRIVATE_KEY_B64="$(base64 -w0 "$tmpdir/private.pem")"
  JWT_PUBLIC_KEY_B64="$(base64 -w0 "$tmpdir/public.pem")"
  rm -rf "$tmpdir"
  trap - EXIT

  umask 077
  cat > "$SECRETS_FILE" <<SECRETS
APP_SECRET=$APP_SECRET
INTERNAL_API_KEY=$INTERNAL_API_KEY
JWT_PASSPHRASE=$JWT_PASSPHRASE
JWT_PRIVATE_KEY_B64=$JWT_PRIVATE_KEY_B64
JWT_PUBLIC_KEY_B64=$JWT_PUBLIC_KEY_B64
REDIS_PASSWORD=$REDIS_PASSWORD
NEW_POSTGRES_PASSWORD=$NEW_POSTGRES_PASSWORD
SECRETS
  chmod 600 "$SECRETS_FILE"
fi

railway whoami >/dev/null 2>&1 || die "Railway CLI non authentifiée. Lance: railway login"
railway status >/dev/null 2>&1 || die "ce dépôt n'est pas lié au projet Railway. Lance: railway link"
railway environment "$RAILWAY_ENVIRONMENT" >/dev/null 2>&1 || die "environnement Railway '$RAILWAY_ENVIRONMENT' introuvable"

service_exists() {
  railway variable list -s "$1" -e "$RAILWAY_ENVIRONMENT" --json >/dev/null 2>&1
}

ensure_empty_service() {
  local name="$1"
  if service_exists "$name"; then
    log "Service existant conservé: $name"
  else
    log "Création du service: $name"
    railway add --service "$name" >/dev/null
  fi
}

set_vars() {
  local service="$1"; shift
  railway variable set -s "$service" -e "$RAILWAY_ENVIRONMENT" --skip-deploys "$@" >/dev/null
}

set_cfg() {
  local service="$1" path="$2" value="$3"
  railway environment edit -e "$RAILWAY_ENVIRONMENT" \
    --service-config "$service" "$path" "$value" \
    -m "GemLink production provisioning: $service $path" >/dev/null
}

ensure_volume() {
  local service="$1" mount="$2"
  local json
  json="$(railway volume list -s "$service" -e "$RAILWAY_ENVIRONMENT" --json 2>/dev/null || printf '[]')"
  if printf '%s' "$json" | grep -Fq "$mount"; then
    log "Volume déjà présent pour $service -> $mount"
  else
    log "Ajout volume $service -> $mount"
    railway volume add -s "$service" -e "$RAILWAY_ENVIRONMENT" --mount-path "$mount" >/dev/null
  fi
}

connect_repo() {
  local service="$1"
  railway service source connect -s "$service" -e "$RAILWAY_ENVIRONMENT" \
    --repo "$GITHUB_REPO" --branch "$GITHUB_BRANCH" >/dev/null
}

connect_image() {
  local service="$1" image="$2"
  railway service source connect -s "$service" -e "$RAILWAY_ENVIRONMENT" \
    --image "$image" >/dev/null
}

# ---------------------------------------------------------------------------
# Bases / infrastructure
# ---------------------------------------------------------------------------
ensure_empty_service pgvector
if ! railway variable list -s pgvector -e "$RAILWAY_ENVIRONMENT" --kv 2>/dev/null | grep -q '^DATABASE_URL='; then
  log "Initialisation d'un nouveau pgvector PostgreSQL 16"
  set_vars pgvector \
    "POSTGRES_DB=gemlink" \
    "POSTGRES_USER=gemlink" \
    "POSTGRES_PASSWORD=$NEW_POSTGRES_PASSWORD" \
    'DATABASE_URL=postgresql://${{POSTGRES_USER}}:${{POSTGRES_PASSWORD}}@${{RAILWAY_PRIVATE_DOMAIN}}:5432/${{POSTGRES_DB}}'
  ensure_volume pgvector /var/lib/postgresql/data
  connect_image pgvector pgvector/pgvector:pg16
else
  log "pgvector possède déjà DATABASE_URL: credentials existants non modifiés"
fi

if service_exists Redis && [ "$RESET_REDIS" = "1" ]; then
  die "RESET_REDIS=1 est volontairement non destructif dans ce script. Renomme/supprime l'ancien Redis dans Railway puis relance; le script recréera Redis 7.2."
fi
ensure_empty_service Redis
# Redis est un cache/transport et peut être reconfiguré sans toucher au volume.
# On remplace aussi l'ancien credential exposé : URL et mot de passe restent cohérents.
set_vars Redis \
  "REDIS_PASSWORD=$REDIS_PASSWORD" \
  'REDIS_URL=redis://default:${{REDIS_PASSWORD}}@${{RAILWAY_PRIVATE_DOMAIN}}:6379'
ensure_volume Redis /data
set_cfg Redis deploy.startCommand 'sh -lc '\''exec redis-server --appendonly yes --requirepass "$REDIS_PASSWORD"'\'''
set_cfg Redis deploy.restartPolicyType ALWAYS
connect_image Redis redis:7.2-alpine

ensure_empty_service Ollama
set_vars Ollama "OLLAMA_HOST=0.0.0.0:11434"
ensure_volume Ollama /root/.ollama
set_cfg Ollama deploy.restartPolicyType ALWAYS
connect_image Ollama ollama/ollama:0.11.10

# ---------------------------------------------------------------------------
# Backend Symfony API
# ---------------------------------------------------------------------------
ensure_empty_service backend
set_vars backend \
  "APP_ENV=prod" \
  "APP_DEBUG=0" \
  "PORT=8080" \
  "APP_SECRET=$APP_SECRET" \
  "APP_URL=https://$BACKEND_DOMAIN" \
  "DEFAULT_URI=https://$BACKEND_DOMAIN" \
  "FRONTEND_URL=$FRONTEND_URL" \
  'TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR' \
  'TRUSTED_HOSTS=^(caverne\.gem-link\.org|.+\.up\.railway\.app|backend\.railway\.internal)$' \
  "CORS_ALLOW_ORIGIN=$CORS_ALLOW_ORIGIN" \
  'DATABASE_URL=${{pgvector.DATABASE_URL}}?serverVersion=16&charset=utf8' \
  'REDIS_URL=${{Redis.REDIS_URL}}' \
  'MESSENGER_TRANSPORT_DSN=${{Redis.REDIS_URL}}/messages' \
  'MESSENGER_LOW_PRIORITY_TRANSPORT_DSN=${{Redis.REDIS_URL}}/identification_low_priority' \
  'MESSENGER_FAILED_TRANSPORT_DSN=doctrine://default?queue_name=failed' \
  'JWT_SECRET_KEY=/tmp/gemlink-jwt/private.pem' \
  'JWT_PUBLIC_KEY=/tmp/gemlink-jwt/public.pem' \
  "JWT_PASSPHRASE=$JWT_PASSPHRASE" \
  "JWT_PRIVATE_KEY_B64=$JWT_PRIVATE_KEY_B64" \
  "JWT_PUBLIC_KEY_B64=$JWT_PUBLIC_KEY_B64" \
  "MAILER_DSN=$MAILER_DSN" \
  "MAILER_FROM_EMAIL=${MAILER_FROM_EMAIL:-contact@gem-link.org}" \
  "MAILER_FROM_NAME=${MAILER_FROM_NAME:-GemLink}" \
  'AI_ENABLED=true' \
  'AI_SERVICE_URL=http://${{FastAPI.RAILWAY_PRIVATE_DOMAIN}}:3000' \
  "INTERNAL_API_KEY=$INTERNAL_API_KEY" \
  'MEDIA_STORAGE_MODE=r2' \
  "R2_ACCOUNT_ID=$R2_ACCOUNT_ID" \
  "R2_ACCESS_KEY_ID=$R2_ACCESS_KEY_ID" \
  "R2_SECRET_ACCESS_KEY=$R2_SECRET_ACCESS_KEY" \
  "R2_BUCKET=$R2_BUCKET" \
  "R2_ENDPOINT=$R2_ENDPOINT" \
  "R2_PUBLIC_BASE_URL=$R2_PUBLIC_BASE_URL" \
  "INFOMANIAK_NEWSLETTER_TOKEN=${INFOMANIAK_NEWSLETTER_TOKEN:-}" \
  "INFOMANIAK_NEWSLETTER_CLIENT_API_URL=${INFOMANIAK_NEWSLETTER_CLIENT_API_URL:-https://api.infomaniak.com/1/newsletter}" \
  "INFOMANIAK_NEWSLETTER_DOMAIN_ID=${INFOMANIAK_NEWSLETTER_DOMAIN_ID:-0}"
set_cfg backend source.rootDirectory /backend
set_cfg backend deploy.startCommand /app/bin/railway-api-start
set_cfg backend deploy.healthcheckPath /health
set_cfg backend deploy.healthcheckTimeout 300
set_cfg backend deploy.restartPolicyType ON_FAILURE
connect_repo backend

# ---------------------------------------------------------------------------
# Worker PHP Symfony Messenger
# ---------------------------------------------------------------------------
ensure_empty_service worker
set_vars worker \
  'APP_ENV=prod' \
  'APP_DEBUG=0' \
  'APP_SECRET=${{backend.APP_SECRET}}' \
  "APP_URL=https://$BACKEND_DOMAIN" \
  "DEFAULT_URI=https://$BACKEND_DOMAIN" \
  "FRONTEND_URL=$FRONTEND_URL" \
  'TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR' \
  'TRUSTED_HOSTS=^(worker\.railway\.internal)$' \
  "CORS_ALLOW_ORIGIN=$CORS_ALLOW_ORIGIN" \
  'DATABASE_URL=${{pgvector.DATABASE_URL}}?serverVersion=16&charset=utf8' \
  'REDIS_URL=${{Redis.REDIS_URL}}' \
  'MESSENGER_TRANSPORT_DSN=${{Redis.REDIS_URL}}/messages' \
  'MESSENGER_LOW_PRIORITY_TRANSPORT_DSN=${{Redis.REDIS_URL}}/identification_low_priority' \
  'MESSENGER_FAILED_TRANSPORT_DSN=doctrine://default?queue_name=failed' \
  'JWT_SECRET_KEY=/tmp/gemlink-jwt/private.pem' \
  'JWT_PUBLIC_KEY=/tmp/gemlink-jwt/public.pem' \
  'JWT_PASSPHRASE=${{backend.JWT_PASSPHRASE}}' \
  'JWT_PRIVATE_KEY_B64=${{backend.JWT_PRIVATE_KEY_B64}}' \
  'JWT_PUBLIC_KEY_B64=${{backend.JWT_PUBLIC_KEY_B64}}' \
  'MAILER_DSN=${{backend.MAILER_DSN}}' \
  'MAILER_FROM_EMAIL=${{backend.MAILER_FROM_EMAIL}}' \
  'MAILER_FROM_NAME=${{backend.MAILER_FROM_NAME}}' \
  'AI_ENABLED=true' \
  'AI_SERVICE_URL=http://${{FastAPI.RAILWAY_PRIVATE_DOMAIN}}:3000' \
  'INTERNAL_API_KEY=${{backend.INTERNAL_API_KEY}}' \
  'MEDIA_STORAGE_MODE=r2' \
  'R2_ACCOUNT_ID=${{backend.R2_ACCOUNT_ID}}' \
  'R2_ACCESS_KEY_ID=${{backend.R2_ACCESS_KEY_ID}}' \
  'R2_SECRET_ACCESS_KEY=${{backend.R2_SECRET_ACCESS_KEY}}' \
  'R2_BUCKET=${{backend.R2_BUCKET}}' \
  'R2_ENDPOINT=${{backend.R2_ENDPOINT}}' \
  'R2_PUBLIC_BASE_URL=${{backend.R2_PUBLIC_BASE_URL}}' \
  'INFOMANIAK_NEWSLETTER_TOKEN=${{backend.INFOMANIAK_NEWSLETTER_TOKEN}}' \
  'INFOMANIAK_NEWSLETTER_CLIENT_API_URL=${{backend.INFOMANIAK_NEWSLETTER_CLIENT_API_URL}}' \
  'INFOMANIAK_NEWSLETTER_DOMAIN_ID=${{backend.INFOMANIAK_NEWSLETTER_DOMAIN_ID}}'
set_cfg worker source.rootDirectory /backend
set_cfg worker deploy.startCommand /app/bin/railway-worker-start
set_cfg worker deploy.restartPolicyType ALWAYS
connect_repo worker

# ---------------------------------------------------------------------------
# FastAPI privé
# ---------------------------------------------------------------------------
ensure_empty_service FastAPI
set_vars FastAPI \
  'APP_ENV=production' \
  'APP_HOST=0.0.0.0' \
  'PORT=3000' \
  'INTERNAL_API_KEY=${{backend.INTERNAL_API_KEY}}' \
  'AI_ENABLED=true' \
  'VISION_ENABLED=true' \
  'LLM_ENABLED=true' \
  'OLLAMA_URL=http://${{Ollama.RAILWAY_PRIVATE_DOMAIN}}:11434' \
  "OLLAMA_TEXT_MODEL=${OLLAMA_TEXT_MODEL:-qwen3:0.6b}" \
  "OLLAMA_VISION_MODEL=${OLLAMA_VISION_MODEL:-moondream}" \
  'DETECTOR_BACKEND=torchvision' \
  'DETECTOR_MODEL_PATH=/app/checkpoints/detector_stones.pth' \
  "DETECTOR_MODEL_VERSION=${DETECTOR_MODEL_VERSION:-stone-detector-v1}" \
  'DETECTION_CONFIDENCE_THRESHOLD=0.5' \
  'VIT_MODEL_PATH=/app/checkpoints/vit_stones.pth' \
  "VIT_MODEL_VERSION=${VIT_MODEL_VERSION:-vit-stones-v1}" \
  'VIT_VERSIONS_ROOT=/app/checkpoints/versions' \
  'FINE_TUNE_STATE_PATH=/app/checkpoints/fine_tuning_state.json' \
  'FINE_TUNE_MAX_LOG_LINES=500' \
  'FINE_TUNE_EPOCHS=15' \
  'CLIP_MODEL_ARCH=ViT-B-32' \
  'CLIP_MODEL_PATH=/app/checkpoints/clip_vit_b_32_openai.pt' \
  'CLIP_MODEL_PRETRAINED=openai' \
  "CLIP_MODEL_VERSION=${CLIP_MODEL_VERSION:-clip-vit-b-32-openai}" \
  'MEDIA_INTERNAL_BASE_URL=http://${{backend.RAILWAY_PRIVATE_DOMAIN}}:8080'
ensure_volume FastAPI /app/checkpoints
set_cfg FastAPI source.rootDirectory /Fastapi
set_cfg FastAPI deploy.healthcheckPath /health
set_cfg FastAPI deploy.healthcheckTimeout 300
set_cfg FastAPI deploy.restartPolicyType ON_FAILURE
connect_repo FastAPI

log "Provisionnement terminé. Les services sont créés/configurés."
cat <<SUMMARY

Services attendus dans Railway/$RAILWAY_ENVIRONMENT:
  - backend      : Symfony API (public)
  - worker       : PHP Symfony Messenger (privé)
  - pgvector     : PostgreSQL 16 + pgvector (privé)
  - Redis        : Redis 7.2 BSD-3-Clause (privé)
  - FastAPI      : service IA (privé)
  - Ollama       : modèles locaux (privé + volume)

Étapes post-déploiement:
  1. Vérifier que l'abonnement Railway est actif.
  2. Vérifier les logs: railway service status --all -e $RAILWAY_ENVIRONMENT
  3. Quand Ollama est Online:
       railway ssh -s Ollama -e $RAILWAY_ENVIRONMENT -- ollama pull ${OLLAMA_TEXT_MODEL:-qwen3:0.6b}
       railway ssh -s Ollama -e $RAILWAY_ENVIRONMENT -- ollama pull ${OLLAMA_VISION_MODEL:-moondream}
  4. Uploader les checkpoints approuvés dans le volume FastAPI.
  5. Ajouter le domaine public uniquement sur backend: $BACKEND_DOMAIN

Aucun secret de .generated-secrets.env ne doit être commité.
SUMMARY
