import asyncio
import json
import os
import re
import shutil
import sys
import tempfile
from collections import defaultdict
from pathlib import Path

import aiohttp
from pydantic import BaseModel, Field, HttpUrl


class FineTuneCandidate(BaseModel):
    media_url: HttpUrl
    label: str = Field(min_length=1, max_length=100)


class FineTuneRequest(BaseModel):
    job_id: str = Field(min_length=1, max_length=100)
    model_version: str = Field(min_length=1, max_length=100)
    candidates: list[FineTuneCandidate] = Field(min_length=4, max_length=500)


jobs: dict[str, dict] = {}


def _safe_label(label: str) -> str:
    value = re.sub(r'[^a-zA-Z0-9_-]+', '_', label.strip()).strip('_')
    if not value:
        raise ValueError('Un label du dataset est invalide.')
    return value[:80]


async def _download(session: aiohttp.ClientSession, url: str, target: Path) -> None:
    internal_base = os.getenv('MEDIA_INTERNAL_BASE_URL', '').rstrip('/')
    if internal_base and '/uploads/' in url:
        url = internal_base + '/uploads/' + url.split('/uploads/', 1)[1]
    async with session.get(url, timeout=aiohttp.ClientTimeout(total=60)) as response:
        response.raise_for_status()
        content = await response.read()
    if not content or len(content) > 10 * 1024 * 1024:
        raise ValueError('Un média candidat est vide ou dépasse 10 Mo.')
    target.write_bytes(content)


async def _prepare_dataset(request: FineTuneRequest, session: aiohttp.ClientSession, root: Path) -> int:
    grouped: dict[str, list[FineTuneCandidate]] = defaultdict(list)
    for candidate in request.candidates:
        grouped[_safe_label(candidate.label)].append(candidate)

    eligible = {label: values for label, values in grouped.items() if len(values) >= 2}
    if len(eligible) < 2:
        raise ValueError('Le dataset doit contenir au moins deux classes avec deux médias chacune.')

    downloads = []
    count = 0
    for label, candidates in eligible.items():
        valid_count = max(1, len(candidates) // 5)
        for index, candidate in enumerate(candidates):
            split = 'valid' if index < valid_count else 'train'
            destination = root / split / label / f'{index}.jpg'
            destination.parent.mkdir(parents=True, exist_ok=True)
            downloads.append(_download(session, str(candidate.media_url), destination))
            count += 1
    await asyncio.gather(*downloads)
    return count


async def run_job(request: FineTuneRequest, session: aiohttp.ClientSession) -> None:
    state = jobs[request.job_id]
    dataset_root = Path(tempfile.mkdtemp(prefix=f'gemlink-{request.job_id}-'))
    try:
        state.update(status='PREPARING', progress=5)
        sample_count = await _prepare_dataset(request, session, dataset_root)
        state.update(status='RUNNING', progress=15, sampleCount=sample_count)

        output_path = Path('checkpoints') / f'{request.job_id}.pth'
        environment = os.environ.copy()
        environment['DATASET_ROOT'] = str(dataset_root)
        environment['FINE_TUNE_OUTPUT_PATH'] = str(output_path)
        process = await asyncio.create_subprocess_exec(
            sys.executable,
            'train_stone_model.py',
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.STDOUT,
            env=environment,
        )
        assert process.stdout is not None
        async for raw_line in process.stdout:
            line = raw_line.decode(errors='replace').strip()
            if line.startswith('PROGRESS:'):
                state['progress'] = min(95, max(15, int(line.split(':', 1)[1])))
            elif line.startswith('METRICS:'):
                state['metrics'] = json.loads(line.split(':', 1)[1])
        return_code = await process.wait()
        if return_code != 0:
            raise RuntimeError('Le processus d’entraînement a échoué.')

        metrics = state.get('metrics', {})
        state.update(status='COMPLETED', progress=100, **metrics)
    except Exception as error:
        state.update(status='FAILED', error=str(error)[:1000])
    finally:
        shutil.rmtree(dataset_root, ignore_errors=True)


def start_job(request: FineTuneRequest, session: aiohttp.ClientSession) -> dict:
    current = jobs.get(request.job_id)
    if current and current.get('status') not in {'FAILED', 'COMPLETED'}:
        return current
    jobs[request.job_id] = {'status': 'QUEUED', 'progress': 1}
    asyncio.create_task(run_job(request, session))
    return jobs[request.job_id]
