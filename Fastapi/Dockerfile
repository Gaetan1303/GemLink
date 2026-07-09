# syntax=docker/dockerfile:1
# ==============================================================================
# STAGE 1 — BUILDER
# Installe les dépendances Python dans un venv isolé. Ce stage contient les
# outils de compilation ; il est jeté à la fin et n'alourdit jamais l'image finale.
# ==============================================================================
FROM python:3.11-slim AS builder

WORKDIR /app

# Outils de build nécessaires pour compiler certaines dépendances Python
# (certaines libs C/C++ n'ont pas toujours de wheel précompilé pour toutes les archs)
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    git \
    && rm -rf /var/lib/apt/lists/*

# Environnement virtuel isolé, copié tel quel dans le stage final
RUN python -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Copié en premier pour profiter du cache Docker : si requirements.txt ne
# change pas, cette couche (la plus longue à builder, torch inclus) est réutilisée
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt


# ==============================================================================
# STAGE 2 — RUNTIME
# Image finale : seulement le nécessaire pour EXÉCUTER l'API, pas pour la builder.
# ==============================================================================
FROM python:3.11-slim AS runtime

WORKDIR /app

# Dépendances système runtime uniquement :
# - libgl1 / libglib2.0-0 : requis par opencv-python (dépendance transitive
#   d'ultralytics/YOLO) — sans ça : ImportError: libGL.so.1
# - curl : utilisé par le HEALTHCHECK ci-dessous
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --shell /bin/bash appuser

# Récupère le venv déjà construit au stage précédent (pas de recompilation ici)
COPY --from=builder /opt/venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH" \
    PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1

# Code applicatif
COPY --chown=appuser:appuser . .

# Dossiers pour les poids de modèles : à monter en volume (Railway Volumes)
# pour persister entre les déploiements sans avoir à réentraîner à chaque fois.
RUN mkdir -p checkpoints runs/detect/stone_detector/weights \
    && chown -R appuser:appuser checkpoints runs

USER appuser

# Railway assigne un $PORT dynamique à chaque déploiement — on ne le fixe jamais
# en dur. En local (docker compose), $PORT sera absent -> fallback sur 3000.
ENV PORT=3000
EXPOSE 3000

HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=5 \
    CMD curl -f http://localhost:${PORT}/health || exit 1

# Forme shell (pas exec) pour permettre l'expansion de ${PORT}
CMD uvicorn main:app --host 0.0.0.0 --port ${PORT} --workers 1