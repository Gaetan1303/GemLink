# ==========================================
# Étape 1 : Le Builder (Compiles & Installs)
# ==========================================
FROM python:3.11-slim AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .

RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir --user -r requirements.txt --extra-index-url https://download.pytorch.org/whl/cpu

# ==========================================
# Étape 2 : Le Runtime (Image finale ultra-légère)
# ==========================================
FROM python:3.11-slim AS runtime

WORKDIR /app


RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /root/.local /root/.local
COPY . .

ENV PATH=/root/.local/bin:$PATH
ENV PYTHONUNBUFFERED=1
ENV YOLO_CONFIG_DIR=/tmp/Ultralytics

RUN mkdir -p /tmp/Ultralytics && chmod 0777 /tmp/Ultralytics

RUN find . -type d -name "__pycache__" -exec rm -r {} + 2>/dev/null || true

EXPOSE 3000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "3000"]
