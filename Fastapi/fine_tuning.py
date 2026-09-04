from __future__ import annotations

import asyncio
import json
import os
import re
import shutil
import sys
import tempfile
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import aiohttp
from pydantic import BaseModel, Field, HttpUrl, model_validator

from app.config import get_settings


TERMINAL_STATUSES = {"FAILED", "COMPLETED"}
settings = get_settings()
MAX_LOG_LINES = max(20, settings.fine_tune_max_log_lines)
STATE_PATH = settings.fine_tune_state_path
VERSIONS_ROOT = settings.vit_versions_root
VERSION_PATTERN = re.compile(r"^vit-v\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _load_state() -> tuple[dict[str, dict[str, Any]], dict[str, dict[str, Any]]]:
    if not STATE_PATH.exists():
        return {}, {}
    try:
        payload = json.loads(STATE_PATH.read_text(encoding="utf-8"))
        loaded_jobs = payload.get("jobs", {})
        loaded_versions = payload.get("versions", {})
        if not isinstance(loaded_jobs, dict) or not isinstance(loaded_versions, dict):
            raise ValueError("Format d'etat invalide")
        # Un sous-processus ne survit pas au redemarrage du service. On expose
        # donc explicitement l'interruption au lieu de laisser un job bloque.
        for state in loaded_jobs.values():
            if state.get("status") not in TERMINAL_STATUSES:
                state.update(
                    status="FAILED",
                    error="Cycle interrompu par le redemarrage du service IA.",
                    completedAt=_now(),
                )
        return loaded_jobs, loaded_versions
    except (OSError, ValueError, TypeError, json.JSONDecodeError):
        return {}, {}


jobs, model_versions = _load_state()


def _persist_state() -> None:
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True)
    temporary = STATE_PATH.with_suffix(STATE_PATH.suffix + ".tmp")
    temporary.write_text(
        json.dumps({"jobs": jobs, "versions": model_versions}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    temporary.replace(STATE_PATH)


class FineTuneCandidate(BaseModel):
    media_url: HttpUrl
    label: str = Field(min_length=1, max_length=100)
    # Champs optionnels pour garder le contrat actuel avec Symfony. Lorsqu'ils
    # sont fournis, FastAPI refait le controle du seuil (defense en profondeur).
    validation_id: str | None = Field(default=None, max_length=100)
    trust_score: int | None = Field(default=None, ge=0, le=100)


class FineTuneRequest(BaseModel):
    job_id: str = Field(min_length=1, max_length=100)
    model_version: str = Field(min_length=1, max_length=100)
    min_trust_score: int | None = Field(default=None, ge=0, le=100)
    candidates: list[FineTuneCandidate] = Field(min_length=4, max_length=500)

    @model_validator(mode="after")
    def validate_version_and_trust_scores(self) -> "FineTuneRequest":
        if not VERSION_PATTERN.fullmatch(self.model_version):
            raise ValueError("model_version doit respecter le format vit-vX.Y.Z.")
        supplied_scores = [candidate.trust_score is not None for candidate in self.candidates]
        if self.min_trust_score is not None and any(supplied_scores) and not all(supplied_scores):
            raise ValueError("Le Trust Score doit etre fourni pour tous les candidats ou pour aucun.")
        return self


def _safe_label(label: str) -> str:
    value = re.sub(r"[^a-zA-Z0-9_-]+", "_", label.strip()).strip("_")
    if not value:
        raise ValueError("Un label du dataset est invalide.")
    return value[:80]


def _checkpoint_path(version: str) -> Path:
    if not VERSION_PATTERN.fullmatch(version):
        raise ValueError("Version ViT invalide.")
    return VERSIONS_ROOT / f"{version}.pth"


def _append_log(state: dict[str, Any], message: str, *, level: str = "INFO") -> None:
    clean_message = message.strip()
    if not clean_message:
        return
    logs = state.setdefault("logs", [])
    logs.append({"timestamp": _now(), "level": level, "message": clean_message[:2000]})
    if len(logs) > MAX_LOG_LINES:
        del logs[:-MAX_LOG_LINES]


def _update_job(job_id: str, **values: Any) -> dict[str, Any]:
    state = jobs[job_id]
    state.update(values, updatedAt=_now())
    _persist_state()
    return state


def _eligible_candidates(request: FineTuneRequest) -> list[FineTuneCandidate]:
    if request.min_trust_score is None:
        return request.candidates
    # Le critere metier dit "au-dessus du seuil" : la comparaison est stricte.
    if all(candidate.trust_score is not None for candidate in request.candidates):
        return [
            candidate
            for candidate in request.candidates
            if candidate.trust_score is not None and candidate.trust_score > request.min_trust_score
        ]
    # Compatibilite avec l'orchestrateur Symfony actuel, qui effectue deja la
    # selection en base et n'envoie pas le score dans son payload interne.
    return request.candidates


def active_checkpoint_path() -> str:
    for metadata in model_versions.values():
        if metadata.get("status") == "ACTIVE" and metadata.get("checkpoint"):
            return str(metadata["checkpoint"])
    return str(settings.vit_model_path)


async def _download(session: aiohttp.ClientSession, url: str, target: Path) -> None:
    internal_base = settings.media_internal_base_url.rstrip("/")
    if internal_base and "/uploads/" in url:
        url = internal_base + "/uploads/" + url.split("/uploads/", 1)[1]
    async with session.get(url, timeout=aiohttp.ClientTimeout(total=60)) as response:
        response.raise_for_status()
        content = await response.read()
    if not content or len(content) > 10 * 1024 * 1024:
        raise ValueError("Un media candidat est vide ou depasse 10 Mo.")
    target.write_bytes(content)


async def _prepare_dataset(
    request: FineTuneRequest,
    session: aiohttp.ClientSession,
    root: Path,
) -> int:
    candidates = _eligible_candidates(request)
    grouped: dict[str, list[FineTuneCandidate]] = defaultdict(list)
    for candidate in candidates:
        grouped[_safe_label(candidate.label)].append(candidate)

    eligible = {label: values for label, values in grouped.items() if len(values) >= 2}
    if len(eligible) < 2:
        raise ValueError("Le dataset doit contenir au moins deux classes avec deux medias chacune.")

    downloads = []
    count = 0
    for label, label_candidates in eligible.items():
        valid_count = max(1, len(label_candidates) // 5)
        for index, candidate in enumerate(label_candidates):
            split = "valid" if index < valid_count else "train"
            destination = root / split / label / f"{index}.jpg"
            destination.parent.mkdir(parents=True, exist_ok=True)
            downloads.append(_download(session, str(candidate.media_url), destination))
            count += 1
    await asyncio.gather(*downloads)
    return count


async def run_job(request: FineTuneRequest, session: aiohttp.ClientSession) -> None:
    state = jobs[request.job_id]
    dataset_root = Path(tempfile.mkdtemp(prefix=f"gemlink-{request.job_id}-"))
    try:
        _append_log(state, "Preparation du dataset candidat.")
        _update_job(request.job_id, status="PREPARING", progress=5, startedAt=_now())
        sample_count = await _prepare_dataset(request, session, dataset_root)
        filtered_count = len(request.candidates) - len(_eligible_candidates(request))
        _append_log(state, f"Dataset pret : {sample_count} medias eligibles.")
        if filtered_count:
            _append_log(state, f"{filtered_count} validation(s) exclue(s) par le seuil de Trust Score.")
        _update_job(
            request.job_id,
            status="RUNNING",
            progress=15,
            sampleCount=sample_count,
            excludedCandidateCount=filtered_count,
        )

        output_path = _checkpoint_path(request.model_version)
        if output_path.exists():
            raise FileExistsError(f"La version {request.model_version} existe deja.")
        output_path.parent.mkdir(parents=True, exist_ok=True)

        environment = os.environ.copy()
        environment["DATASET_ROOT"] = str(dataset_root)
        environment["FINE_TUNE_OUTPUT_PATH"] = str(output_path)
        environment["FINE_TUNE_MODEL_VERSION"] = request.model_version
        environment["FINE_TUNE_BASE_MODEL_PATH"] = active_checkpoint_path()
        process = await asyncio.create_subprocess_exec(
            sys.executable,
            "train_stone_model.py",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.STDOUT,
            env=environment,
        )
        assert process.stdout is not None
        async for raw_line in process.stdout:
            line = raw_line.decode(errors="replace").strip()
            if line.startswith("PROGRESS:"):
                progress = min(95, max(15, int(line.split(":", 1)[1])))
                _update_job(request.job_id, progress=progress)
            elif line.startswith("METRICS:"):
                metrics = json.loads(line.split(":", 1)[1])
                state["metrics"] = metrics
                _persist_state()
            else:
                _append_log(state, line)
                _persist_state()
        return_code = await process.wait()
        if return_code != 0:
            raise RuntimeError("Le processus d'entrainement a echoue.")
        if not output_path.is_file():
            raise RuntimeError("Le processus n'a produit aucun checkpoint.")

        metrics = state.get("metrics", {})
        completed_at = _now()
        model_versions[request.model_version] = {
            "version": request.model_version,
            "status": "READY",
            "checkpoint": str(output_path),
            "accuracy": metrics.get("accuracy"),
            "f1Score": metrics.get("f1Score"),
            "sampleCount": sample_count,
            "createdAt": completed_at,
        }
        _append_log(state, f"Checkpoint versionne disponible : {request.model_version}.")
        _update_job(
            request.job_id,
            status="COMPLETED",
            progress=100,
            completedAt=completed_at,
            checkpoint=str(output_path),
            **{key: value for key, value in metrics.items() if key != "checkpoint"},
        )
    except Exception as error:
        _append_log(state, str(error), level="ERROR")
        _update_job(
            request.job_id,
            status="FAILED",
            error=str(error)[:1000],
            completedAt=_now(),
        )
    finally:
        shutil.rmtree(dataset_root, ignore_errors=True)


def start_job(request: FineTuneRequest, session: aiohttp.ClientSession) -> dict[str, Any]:
    current = jobs.get(request.job_id)
    if current and current.get("status") not in TERMINAL_STATUSES:
        return current
    if any(state.get("status") not in TERMINAL_STATUSES for state in jobs.values()):
        raise ValueError("Un cycle de fine-tuning est deja en cours.")
    if request.model_version in model_versions or _checkpoint_path(request.model_version).exists():
        raise ValueError(f"La version {request.model_version} existe deja.")

    now = _now()
    jobs[request.job_id] = {
        "jobId": request.job_id,
        "modelVersion": request.model_version,
        "status": "QUEUED",
        "progress": 1,
        "candidateCount": len(request.candidates),
        "minTrustScore": request.min_trust_score,
        "logs": [],
        "createdAt": now,
        "updatedAt": now,
    }
    _append_log(jobs[request.job_id], "Cycle de fine-tuning mis en file d'attente.")
    _persist_state()
    asyncio.create_task(run_job(request, session))
    return jobs[request.job_id]


def register_active_version(version: str, checkpoint: str) -> None:
    for metadata in model_versions.values():
        if metadata.get("status") == "ACTIVE":
            metadata["status"] = "DEPRECATED"
    existing = model_versions.get(version, {})
    model_versions[version] = {
        **existing,
        "version": version,
        "checkpoint": checkpoint,
        "status": "ACTIVE",
        "activatedAt": _now(),
    }
    _persist_state()


def versions() -> list[dict[str, Any]]:
    return sorted(
        (dict(metadata) for metadata in model_versions.values()),
        key=lambda metadata: metadata.get("createdAt", metadata.get("activatedAt", "")),
        reverse=True,
    )
