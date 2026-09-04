# Production licenses

Audit date: 2026-09-04. `PROD_OK` means that the identified artifact uses one
of GemLink's preferred licenses. It is not a substitute for legal advice.
`TO_VERIFY` blocks that artifact from a production release until the owner has
recorded an explicit approval. Versions are taken from lock files or deployment
configuration; Python ranges must be frozen into an image digest at release.

| Name | Version/artifact | Use in GemLink | License | Official source | Status |
|---|---|---|---|---|---|
| GemLink application code | repository | Product | MIT | [`LICENSE`](../LICENSE) | PROD_OK |
| Symfony Framework | 8.1.1 | API and worker | MIT | https://github.com/symfony/symfony/blob/8.1/LICENSE | PROD_OK |
| API Platform | 4.3.15 | REST metadata | MIT | https://github.com/api-platform/core/blob/v4.3.15/LICENSE | PROD_OK |
| Doctrine ORM | 3.6.7 | Persistence | MIT | https://github.com/doctrine/orm/blob/3.6.x/LICENSE | PROD_OK |
| Flysystem AWS S3 adapter | 3.35.1 | R2 storage | MIT | https://github.com/thephpleague/flysystem-aws-s3-v3/blob/3.x/LICENSE | PROD_OK |
| AWS SDK for PHP | 3.394.0 | R2 S3 client | Apache-2.0 | https://github.com/aws/aws-sdk-php/blob/master/LICENSE | PROD_OK |
| Guzzle | 8.1.0 | HTTP transport used by the AWS SDK | MIT | https://github.com/guzzle/guzzle/blob/8.1/LICENSE | PROD_OK |
| Angular | 21.2.21 | Browser application | MIT | https://github.com/angular/angular/blob/main/LICENSE | PROD_OK |
| Angular Material | 21.2.14 | UI components | MIT | https://github.com/angular/components/blob/main/LICENSE | PROD_OK |
| RxJS | 7.8.2 | Frontend reactive runtime | Apache-2.0 | https://github.com/ReactiveX/rxjs/blob/7.8.x/LICENSE.txt | PROD_OK |
| Fontsource Plus Jakarta Sans / Material Icons | 5.3.0 | Self-hosted browser fonts | OFL-1.1, outside preferred list | https://fontsource.org/ | TO_VERIFY |
| FastAPI | 0.141.1 | Private AI HTTP API | MIT | https://github.com/fastapi/fastapi/blob/master/LICENSE | PROD_OK |
| Uvicorn | 0.52.4 | ASGI server | BSD-3-Clause | https://github.com/encode/uvicorn/blob/master/LICENSE.md | PROD_OK |
| Pydantic / pydantic-settings | 2.13.5 / 2.15.0 | Validation and configuration | MIT | https://github.com/pydantic/pydantic/blob/main/LICENSE | PROD_OK |
| PyTorch code and binary distribution | 2.14.0+cpu | ViT, detector, embeddings | BSD-3-Clause core plus bundled third-party terms | https://github.com/pytorch/pytorch/blob/main/LICENSE | TO_VERIFY |
| Torchvision code | 0.29.0+cpu | ViT and SSDLite architecture | BSD-3-Clause | https://github.com/pytorch/vision/blob/main/LICENSE | PROD_OK |
| SSDLite320 MobileNet V3 architecture | torchvision | CPU detector architecture | BSD-3-Clause | https://docs.pytorch.org/vision/main/models/generated/torchvision.models.detection.ssdlite320_mobilenet_v3_large.html | PROD_OK |
| Torchvision COCO detector weights | not used | Explicitly excluded from runtime/training | Dataset-derived terms require review | https://github.com/pytorch/vision#pre-trained-model-license | DEV_ONLY |
| GemLink SSDLite checkpoint | `detector_stones.pth` | Stone localization | No checkpoint is shipped; future license follows approved manifest | internal artifact | TO_VERIFY |
| GemLink ViT architecture | ViT-B/16 | Stone classification | BSD-3-Clause implementation | https://pytorch.org/vision/stable/models/generated/torchvision.models.vit_b_16.html | PROD_OK |
| `ViT_B_16_Weights.IMAGENET1K_V1` | not loaded by production runtime | Historical fine-tuning origin | ImageNet-derived terms | https://github.com/pytorch/vision#pre-trained-model-license | TO_VERIFY |
| GemLink ViT checkpoint | `vit_stones.pth` | Fine-tuned classifier | Provenance manifest absent from repository | internal artifact | TO_VERIFY |
| open-clip-torch code | 3.3.0 | 512D pgvector embeddings | MIT | https://github.com/mlfoundations/open_clip/blob/main/LICENSE | PROD_OK |
| OpenAI CLIP ViT-B/32 weights | local checkpoint only; tag `openai` is provenance metadata | 512D pgvector embeddings; no implicit download | MIT repository; training corpus not published as a reconstructible manifest | https://github.com/openai/CLIP/blob/main/LICENSE | TO_VERIFY |
| Qwen3 0.6B weights | `qwen3:0.6b` | Ollama text enrichment | Apache-2.0 | https://huggingface.co/Qwen/Qwen3-0.6B | PROD_OK |
| Moondream 2 weights | Ollama `moondream` | Optional vision fallback | Apache-2.0 | https://huggingface.co/vikhyatk/moondream2 | PROD_OK |
| Ollama | 0.11.10 image configuration | Private model server | MIT | https://github.com/ollama/ollama/blob/main/LICENSE | PROD_OK |
| Redis | 7.2.x only | Cache and Messenger | BSD-3-Clause | https://redis.io/legal/licenses/ | PROD_OK |
| Redis 7.4+ / 8+ | not allowed | Explicitly excluded | RSAL/SSPL and/or AGPL options | https://redis.io/legal/licenses/ | TO_REPLACE |
| PostgreSQL | 16 | Database | PostgreSQL License (permissive, BSD-like but not one of the four named preferred SPDX licenses) | https://www.postgresql.org/about/licence/ | TO_VERIFY |
| pgvector | pg16 image / PHP 0.2.2 | Vector storage | PostgreSQL License | https://github.com/pgvector/pgvector/blob/master/LICENSE | TO_VERIFY |
| Cloudflare R2 / Workers | managed service | Media and frontend CDN | Service terms, not an OSS license | https://www.cloudflare.com/terms/ | TO_VERIFY |
| Railway | managed service | Runtime platform | Service terms, not an OSS license | https://railway.com/legal/terms | TO_VERIFY |
| Ultralytics / YOLO checkpoints | removed | Historical detector | AGPL/commercial and unidentified checkpoint provenance | https://www.ultralytics.com/license | TO_REPLACE |
| Nech-C/mineralimage5K-98 | not accepted for production | Historical dataset source | Repository does not contain a complete per-image approved manifest | https://huggingface.co/datasets/Nech-C/mineralimage5K-98 | TO_VERIFY |

## Release gates

Production remains blocked for the detector and classifier until every training
image has a validated provenance record and new checkpoints are generated. The
current 512D CLIP path is kept to avoid a silent pgvector contract change, but
its OpenAI-weight approval must be recorded or replaced together with a real
database migration and embedding regeneration job. PyTorch wheel notices,
PostgreSQL/pgvector licenses and provider terms also require owner approval.

The training validator accepts only `CC0-1.0`, `PDM-1.0`, `CC-BY-3.0` and
`CC-BY-4.0`; CC-BY attribution must be preserved from the manifest. NC, ND,
unknown, all-rights-reserved and research-only inputs are rejected.
