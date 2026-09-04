#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${RAILWAY_ENVIRONMENT:-production}"
BRANCH="${RAILWAY_PRODUCTION_BRANCH:-prod}"
REPO="${GEMLINK_REPO:-}"

API_SERVICE="${GEMLINK_API_SERVICE:-gemlink-api}"
WORKER_SERVICE="${GEMLINK_WORKER_SERVICE:-gemlink-worker}"
AI_SERVICE="${GEMLINK_AI_SERVICE:-gemlink-ai}"

if ! command -v railway >/dev/null 2>&1; then
  echo 'Railway CLI is required: https://docs.railway.com/cli' >&2
  exit 127
fi

if [[ -z "$REPO" ]]; then
  echo 'Set GEMLINK_REPO=owner/repository before running this script.' >&2
  exit 64
fi

echo "Configuring Railway environment: $ENVIRONMENT"
echo "Production source branch: $BRANCH"

# Connect the three code services to the same monorepo and production branch.
railway service source connect --repo "$REPO" --branch "$BRANCH" --service "$API_SERVICE" --environment "$ENVIRONMENT"
railway service source connect --repo "$REPO" --branch "$BRANCH" --service "$WORKER_SERVICE" --environment "$ENVIRONMENT"
railway service source connect --repo "$REPO" --branch "$BRANCH" --service "$AI_SERVICE" --environment "$ENVIRONMENT"

# Monorepo roots.
railway environment edit -e "$ENVIRONMENT" -y \
  --service-config "$API_SERVICE" source.rootDirectory /backend \
  --service-config "$WORKER_SERVICE" source.rootDirectory /backend \
  --service-config "$AI_SERVICE" source.rootDirectory /Fastapi

# Runtime commands. The API wrapper materializes JWT files and applies Doctrine
# migrations before starting FrankenPHP. Worker uses the same JWT bootstrap.
railway environment edit -e "$ENVIRONMENT" -y \
  --service-config "$API_SERVICE" deploy.startCommand './bin/railway-api-start' \
  --service-config "$API_SERVICE" deploy.healthcheckPath /health \
  --service-config "$API_SERVICE" deploy.healthcheckTimeout 300 \
  --service-config "$API_SERVICE" deploy.restartPolicyType ON_FAILURE \
  --service-config "$WORKER_SERVICE" deploy.startCommand './bin/railway-worker-start' \
  --service-config "$WORKER_SERVICE" deploy.restartPolicyType ON_FAILURE \
  --service-config "$AI_SERVICE" deploy.healthcheckPath /health \
  --service-config "$AI_SERVICE" deploy.healthcheckTimeout 300 \
  --service-config "$AI_SERVICE" deploy.restartPolicyType ON_FAILURE

cat <<MSG

Base service configuration staged/applied.

Next in Railway UI:
  1. Paste deploy/railway/gemlink-api.variables.example into gemlink-api Variables.
  2. Paste deploy/railway/gemlink-worker.variables.example into gemlink-worker Variables.
  3. Paste deploy/railway/gemlink-ai.variables.example into gemlink-ai Variables.
  4. Create and seal shared secrets listed in shared.variables.example.
  5. Enable GitHub Autodeploy + Wait for CI on branch '$BRANCH' for api/worker/ai.
  6. Give ONLY gemlink-api a public/custom domain (caverne.gem-link.org).
  7. Keep gemlink-ai, gemlink-worker, Redis, pgvector and Ollama private.
  8. Attach persistent volumes to Ollama and AI checkpoints.

Do not paste any of the credentials previously exposed in chat. Rotate them first.
MSG
