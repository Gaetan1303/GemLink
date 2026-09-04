# ==========================================
# Étape 1 : Le Builder (Compiles & Installs)
# ==========================================
FROM python:3.12-slim AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt requirements.lock ./

RUN python -m venv /opt/venv && \
    /opt/venv/bin/pip install --no-cache-dir --upgrade "pip==26.2.1" "setuptools==84.0.0" && \
    /opt/venv/bin/pip install --no-cache-dir -r requirements.lock

# ==========================================
# Étape 2 : Le Runtime (Image finale ultra-légère)
# ==========================================
FROM python:3.12-slim AS runtime

WORKDIR /app


RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /opt/venv /opt/venv
COPY . .

ENV PATH=/opt/venv/bin:$PATH
ENV PYTHONUNBUFFERED=1
RUN find . -type d -name "__pycache__" -exec rm -r {} + 2>/dev/null || true

RUN useradd --create-home --uid 10001 gemlink && mkdir -p /app/checkpoints && chown -R gemlink:gemlink /app

USER gemlink

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:3000/health', timeout=4)"

CMD ["sh", "-c", "exec uvicorn main:app --host 0.0.0.0 --port ${PORT:-3000}"]
