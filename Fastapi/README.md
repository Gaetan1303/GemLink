# GemLink AI

Private FastAPI service used only by the Symfony API and Messenger worker. It
combines a Torchvision SSDLite detector, GemLink's ViT classifier, a
512-dimensional CLIP embedding and private Ollama models.

## Development

```bash
python -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.lock
cp .env.example .env
ollama pull qwen3:0.6b
ollama pull moondream
uvicorn main:app --host 127.0.0.1 --port 3000
```

`requirements.txt` contient les dépendances directes revues ;
`requirements.lock` fige l'arbre transitif réellement installé en production.

Set a local `INTERNAL_API_KEY` of at least 32 characters. The service starts in
degraded mode when a model is absent; internal inference endpoints then return
an explicit readiness error while `/health` remains available.

## Production model files

Mount, do not bake, the reviewed files below into `/app/checkpoints`:

- `detector_stones.pth`: checkpoint emitted by
  `training/train_detector_torchvision.py`;
- `vit_stones.pth`: versioned GemLink ViT checkpoint whose training manifest
  has been reviewed.

Legacy Ultralytics/YOLO `.pt` files are intentionally incompatible with the
Torchvision loader and result in `DETECTOR_NOT_READY`.

## Provenance-aware detector training

Install offline training dependencies:

```bash
python -m pip install -r requirements-training.txt
```

Every image must have a JSONL record matching
`training/dataset_manifest.example.jsonl`. Only CC0, Public Domain Mark and
approved CC-BY records are accepted. Unknown, NC, ND, all-rights-reserved and
research-only records fail validation.

Convert the current normalized YOLO annotations to provenance-aware COCO JSON,
then train SSDLite without downloading third-party pretrained weights:

```bash
python training/convert_yolo_dataset.py \
  --dataset-root data/detection \
  --manifest dataset_manifest.jsonl \
  --output data/detection/annotations.coco.json

python training/train_detector_torchvision.py \
  --dataset-root data/detection \
  --annotations data/detection/annotations.coco.json \
  --output checkpoints/detector_stones.pth
```

## Tests

```bash
python -m compileall .
pytest -q tests_gemlink
```

The service must not receive a Railway public domain. Symfony calls it through
Railway private networking with `X-Internal-Key`; browser CORS is unnecessary.
