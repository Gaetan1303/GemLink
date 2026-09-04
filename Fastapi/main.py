import asyncio
import io
import os
import json
import logging
import secrets
import tempfile
from contextlib import asynccontextmanager

from fastapi import FastAPI, File, UploadFile, HTTPException, Request, Header, Depends
from fastapi.responses import JSONResponse, StreamingResponse
import torch
from torchvision import transforms
from PIL import Image
import aiohttp
import cv2

from app.config import get_settings
from inference.detector import DetectorNotReadyError, TorchvisionDetector
from vit import get_model
from utils import crop_with_padding
from schema import StoneAnalysisResponse
from fine_tuning import (
    FineTuneRequest,
    jobs as fine_tuning_jobs,
    model_versions as fine_tuned_versions,
    register_active_version,
    start_job,
    versions as list_vit_versions,
)
import open_clip

# Configuration des logs
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger("GemLinkAI")

settings = get_settings()

VIT_MODEL_PATH = str(settings.vit_model_path)
VIT_MODEL_VERSION = settings.vit_model_version
CLIP_MODEL_ARCH = settings.clip_model_arch
CLIP_MODEL_PATH = str(settings.clip_model_path)
CLIP_MODEL_PRETRAINED = settings.clip_model_pretrained
CLIP_MODEL_VERSION = settings.clip_model_version
DETECTION_CONFIDENCE_THRESHOLD = settings.detection_confidence_threshold
MODEL_VERSIONS = {
    # The historical API key is retained to avoid changing the Symfony contract.
    "yolo": settings.detector_model_version,
    "vit": VIT_MODEL_VERSION,
    "clip": CLIP_MODEL_VERSION,
}

# Clé partagée avec Symfony : ce service ne doit JAMAIS être appelable
# directement par le frontend Angular, uniquement par le backend Symfony.
INTERNAL_API_KEY = settings.internal_api_key_value
INTERNAL_API_KEY_CONFIGURED = settings.internal_api_key_configured
if not INTERNAL_API_KEY_CONFIGURED:
    logger.critical(
        'INTERNAL_API_KEY absente ou trop faible : le service démarre en mode dégradé '
        'et tous les endpoints internes restent bloqués.'
    )


MAX_IMAGE_BYTES = settings.max_image_bytes
MAX_VIDEO_BYTES = settings.max_video_bytes


async def verify_internal_key(x_internal_key: str = Header(None)):
    """Dépendance FastAPI : vérifie que l'appel vient bien de Symfony (ou d'un service autorisé)."""
    if not INTERNAL_API_KEY_CONFIGURED:
        raise HTTPException(status_code=503, detail='Service IA non configuré : clé interne absente.')
    if not x_internal_key or not secrets.compare_digest(x_internal_key, INTERNAL_API_KEY):
        raise HTTPException(status_code=403, detail="Accès interdit : clé interne manquante ou invalide.")


def decode_video_frame(contents: bytes) -> Image.Image:
    if len(contents) > MAX_VIDEO_BYTES:
        raise ValueError('La vidéo dépasse la taille maximale de 100 Mo.')

    temporary_path = ''
    try:
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as temporary:
            temporary.write(contents)
            temporary_path = temporary.name
        capture = cv2.VideoCapture(temporary_path)
        if not capture.isOpened():
            raise ValueError('La vidéo ne peut pas être décodée.')
        try:
            frame_count = int(capture.get(cv2.CAP_PROP_FRAME_COUNT))
            if frame_count > 1:
                capture.set(cv2.CAP_PROP_POS_FRAMES, frame_count // 2)
            success, frame = capture.read()
        finally:
            capture.release()
        if not success or frame is None:
            raise ValueError('Aucune image exploitable trouvée dans la vidéo.')
        return Image.fromarray(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB))
    finally:
        if temporary_path and os.path.exists(temporary_path):
            os.unlink(temporary_path)


# ==============================================================================
# GESTION DU CYCLE DE VIE DES MODÈLES (LIFESPAN)
# ==============================================================================
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load optional AI components without taking the HTTP service down."""
    logger.info("Démarrage de GemLink AI")
    device = "cuda" if torch.cuda.is_available() else "cpu"
    logger.info("Exécution configurée sur %s", device.upper())

    app.state.device = device
    app.state.http_session = aiohttp.ClientSession()
    app.state.model_versions = MODEL_VERSIONS.copy()
    app.state.model_activation_lock = asyncio.Lock()
    app.state.readiness = {
        "detector": False,
        "classifier": False,
        "embeddings": False,
        "llm": False,
    }

    if settings.ai_enabled and settings.vision_enabled:
        # Une activation/rollback survit au redemarrage si son checkpoint est
        # encore disponible ; sinon la version configuree reste le fallback.
        startup_vit_version = VIT_MODEL_VERSION
        startup_vit_path = VIT_MODEL_PATH
        for version, metadata in fine_tuned_versions.items():
            registered_path = str(metadata.get('checkpoint', ''))
            if metadata.get('status') == 'ACTIVE' and os.path.isfile(registered_path):
                startup_vit_version = version
                startup_vit_path = registered_path
                break
        try:
            checkpoint = torch.load(startup_vit_path, map_location=device, weights_only=True)
            if not isinstance(checkpoint, dict) or "model" not in checkpoint or "classes" not in checkpoint:
                raise ValueError("checkpoint ViT incompatible")
            app.state.classes = checkpoint["classes"]
            app.state.classifier_model = get_model(num_classes=len(app.state.classes))
            app.state.classifier_model.load_state_dict(checkpoint["model"], strict=True)
            app.state.classifier_model.to(device)
            app.state.classifier_model.eval()
            app.state.model_versions["vit"] = startup_vit_version
            app.state.preprocess = transforms.Compose([
                transforms.Resize((224, 224)),
                transforms.ToTensor(),
                transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
            ])
            app.state.readiness["classifier"] = True
            register_active_version(startup_vit_version, startup_vit_path)
        except Exception as error:
            logger.error("Classifieur ViT indisponible: %s", type(error).__name__)

        try:
            app.state.detector = TorchvisionDetector(settings.detector_model_path, device)
            app.state.readiness["detector"] = True
        except DetectorNotReadyError:
            logger.error("DETECTOR_NOT_READY")
        except Exception as error:
            logger.error("DETECTOR_NOT_READY: %s", type(error).__name__)

        try:
            if not settings.clip_model_path.is_file():
                raise FileNotFoundError("checkpoint CLIP absent")
            clip_model, _, clip_preprocess = open_clip.create_model_and_transforms(
                CLIP_MODEL_ARCH,
                pretrained=None,
                load_weights=False,
            )
            open_clip.load_checkpoint(clip_model, CLIP_MODEL_PATH, strict=True, weights_only=True)
            clip_model.to(device)
            clip_model.eval()
            app.state.clip_model = clip_model
            app.state.clip_preprocess = clip_preprocess
            app.state.readiness["embeddings"] = True
        except Exception as error:
            logger.error("Modèle d'embeddings indisponible: %s", type(error).__name__)

    yield
    # Nettoyage à l'arrêt du conteneur
    logger.info(" Fermeture de GemLink AI - Libération des ressources...")
    await app.state.http_session.close()
    if hasattr(app.state, "classifier_model"):
        del app.state.classifier_model
    if hasattr(app.state, "detector"):
        del app.state.detector
    if hasattr(app.state, "clip_model"):
        del app.state.clip_model


app = FastAPI(title="GemLink AI Orchestrator API", version="1.1.0", lifespan=lifespan)


@app.post('/fine-tune', tags=['Entraînement'], dependencies=[Depends(verify_internal_key)], status_code=202)
async def fine_tune(request: FineTuneRequest):
    if settings.app_env == "production":
        raise HTTPException(
            status_code=503,
            detail="FINE_TUNING_DISABLED: provenance individuelle requise en production.",
        )
    try:
        return start_job(request, app.state.http_session)
    except ValueError as error:
        raise HTTPException(status_code=409, detail=str(error)) from error


@app.get('/fine-tune/{job_id}', tags=['Entraînement'], dependencies=[Depends(verify_internal_key)])
async def fine_tune_status(job_id: str):
    state = fine_tuning_jobs.get(job_id)
    if state is None:
        raise HTTPException(status_code=404, detail='Job de fine-tuning introuvable.')
    return state


@app.get('/models/vit', tags=['Entraînement'], dependencies=[Depends(verify_internal_key)])
async def vit_versions():
    """Liste les checkpoints ViT disponibles, dont la version active."""
    return list_vit_versions()


def _load_vit_checkpoint(checkpoint_path: str):
    checkpoint = torch.load(checkpoint_path, map_location=app.state.device, weights_only=True)
    if not isinstance(checkpoint, dict) or 'model' not in checkpoint or 'classes' not in checkpoint:
        raise ValueError('Le checkpoint ViT est invalide.')
    classes = checkpoint['classes']
    if not isinstance(classes, list) or not classes:
        raise ValueError('Le checkpoint ViT ne contient aucune classe.')
    model = get_model(num_classes=len(classes))
    model.load_state_dict(checkpoint['model'])
    model.to(app.state.device)
    model.eval()
    return model, classes


@app.post('/models/vit/{version}/activate', tags=['Entraînement'], dependencies=[Depends(verify_internal_key)])
async def activate_vit_version(version: str):
    """
    Active atomiquement un checkpoint versionné. Le même endpoint permet le
    rollback : il suffit de lui passer le nom d'une version antérieure.
    """
    metadata = fine_tuned_versions.get(version)
    if metadata is None:
        raise HTTPException(status_code=404, detail='Version ViT introuvable.')
    checkpoint_path = str(metadata.get('checkpoint', ''))
    if not checkpoint_path or not os.path.isfile(checkpoint_path):
        raise HTTPException(status_code=409, detail='Le checkpoint de cette version est indisponible.')

    async with app.state.model_activation_lock:
        try:
            model, classes = await asyncio.to_thread(_load_vit_checkpoint, checkpoint_path)
        except Exception as error:
            logger.error('Impossible de charger la version ViT %s : %s', version, error, exc_info=True)
            raise HTTPException(status_code=422, detail='Le checkpoint ViT est invalide ou incompatible.') from error

        # Le nouveau modèle est entièrement chargé avant l'échange des
        # références : aucune requête d'inférence ne voit un état partiel.
        previous_model = app.state.classifier_model
        app.state.classifier_model = model
        app.state.classes = classes
        app.state.model_versions['vit'] = version
        register_active_version(version, checkpoint_path)
        del previous_model

    return fine_tuned_versions[version]


# ==============================================================================
# SECTION ROUTAGE : CHAT / LLM (OLLAMA PROXY)
# ==============================================================================

@app.post("/api/chat", tags=["LLM Chat"], dependencies=[Depends(verify_internal_key)])
async def chat_proxy(request: Request):
    """Transfère les requêtes de chat à Ollama en préservant le streaming."""
    if not settings.ai_enabled or not settings.llm_enabled:
        raise HTTPException(status_code=503, detail="LLM_NOT_READY")
    try:
        body = await request.json()
        body["model"] = settings.ollama_text_model

        ollama_response = await app.state.http_session.post(
            f"{settings.ollama_url.rstrip('/')}/api/chat",
            json=body
        )

        async def stream_generator():
            async for chunk in ollama_response.content.iter_any():
                yield chunk

        return StreamingResponse(
            stream_generator(),
            status_code=ollama_response.status,
            media_type="application/x-ndjson"
        )

    except aiohttp.ClientConnectorError:
        logger.error(" Impossible de joindre le conteneur Ollama. Vérifiez son statut.")
        raise HTTPException(status_code=503, detail="Le service de langage (Ollama) est indisponible.")
    except Exception as e:
        logger.error(f" Erreur sur l'endpoint /api/chat : {str(e)}")
        raise HTTPException(status_code=500, detail="Erreur lors de la communication avec le LLM.")


# ==============================================================================
# SECTION ROUTAGE : PIPELINE VISION (Torchvision + ViT)
# ==============================================================================

def run_vision_pipeline(image: Image.Image) -> dict:
    """
    Exécute la détection Torchvision puis la classification ViT sur une image.
    Réutilisée par /predict (rapide, agent vision seul) et /analyze (agent vision + agent connaissance).
    Lève ValueError si aucun minéral n'est détecté.
    """
    readiness = getattr(app.state, "readiness", {})
    if not all(readiness.get(component, False) for component in ("detector", "classifier", "embeddings")):
        raise DetectorNotReadyError("DETECTOR_NOT_READY")

    detector_results = app.state.detector.detect(image, DETECTION_CONFIDENCE_THRESHOLD)
    if not detector_results:
        logger.warning(
            "Le détecteur n'a localisé aucun minéral (seuil=%.2f).",
            DETECTION_CONFIDENCE_THRESHOLD,
        )
        raise ValueError("Aucun minéral localisé dans l'image.")

    detections = []
    for detected in detector_results:
        det_conf = detected.confidence
        bbox = detected.bbox
        crop_img = crop_with_padding(image, bbox)
        input_tensor = app.state.preprocess(crop_img).unsqueeze(0).to(app.state.device)

        with torch.inference_mode():
            outputs = app.state.classifier_model(input_tensor)
            probabilities = torch.nn.functional.softmax(outputs[0], dim=0)
            top_prob, top_idx = torch.max(probabilities, dim=0)

        detections.append({
            "bbox": [int(round(v)) for v in bbox],
            "detector_confidence": det_conf,
            "label": app.state.classes[top_idx.item()],
            "confidence": float(top_prob.item()),
            "embedding": compute_clip_embedding(crop_img),
            "all_probabilities": {
                app.state.classes[i]: float(probabilities[i].item())
                for i in range(len(app.state.classes))
            },
        })

    if not detections:
        raise ValueError("Aucun minéral détecté avec une confiance supérieure à 0.5.")

    detections.sort(key=lambda item: item["detector_confidence"], reverse=True)
    primary = detections[0]

    return {
        "bbox": primary["bbox"],
        "detector_confidence": primary["detector_confidence"],
        "predicted_class": primary["label"],
        "confidence": primary["confidence"],
        "embedding": primary["embedding"],
        "all_probabilities": primary["all_probabilities"],
        "detections": detections,
        "model_version": getattr(app.state, "model_versions", MODEL_VERSIONS).copy(),
    }


def compute_clip_embedding(crop_img: Image.Image) -> list[float]:
    """
    Génère l'embedding CLIP ViT-B/32 (512D) sur le crop déjà produit —
    pas de nouveau découpage, on réutilise crop_img de run_vision_pipeline.
    L2-normalisé pour que la similarité cosinus se calcule comme un simple
    produit scalaire côté pgvector (index HNSW).
    """
    image_input = app.state.clip_preprocess(crop_img).unsqueeze(0).to(app.state.device)

    with torch.inference_mode():
        features = app.state.clip_model.encode_image(image_input)
        features = features / features.norm(dim=-1, keepdim=True).clamp_min(1e-12)

    return features.squeeze(0).cpu().tolist()


# ==============================================================================
# SECTION ROUTAGE : ORCHESTRATION D'IDENTIFICATION
# ==============================================================================

def build_vision_analysis_response(vision_result: dict) -> dict:
    """Construit la réponse contractuelle sans attendre le LLM.

    L'identification (détection, ViT et embedding CLIP) est disponible dès que la
    vision a terminé. Les informations encyclopédiques ne sont pas nécessaires
    pour identifier le spécimen et ne doivent donc pas bloquer Messenger.
    """
    return {
        "nom": vision_result["predicted_class"],
        "confidence": vision_result["confidence"],
        "detector_confidence": vision_result["detector_confidence"],
        "bbox": vision_result["bbox"],
        "embedding": vision_result["embedding"],
        "detections": vision_result["detections"],
        "model_version": vision_result["model_version"],
    }

# Prompt few-shot : les petits modèles, adaptés à la contrainte RAM du
# serveur de prod) respectent un schéma JSON strict beaucoup plus fiablement
# avec un exemple complet à imiter qu'avec une simple description abstraite
# de structure. Les consignes ci-dessous ciblent précisément les erreurs
# observées en prod : clés accentuées, nombres au lieu de chaînes, blocs
# entiers ("physique"/"optique") omis.
ANALYSIS_PROMPT_TEMPLATE = """Tu es un minéralogiste expert. On te donne le nom d'une pierre : "{class_name}".

Réponds UNIQUEMENT avec un objet JSON valide (aucun texte avant/après, aucun bloc markdown).

RÈGLES STRICTES (ne jamais les enfreindre) :
1. Les clés JSON sont EXACTEMENT celles de l'exemple ci-dessous, sans accent, sans espace, en minuscules (ex: "durete", jamais "dureté").
2. TOUTES les valeurs de "physique" et "optique" sont des chaînes de caractères entre guillemets, même les nombres (ex: "densite": "3.06", jamais "densite": 3.06).
3. Les objets "physique" et "optique" sont TOUJOURS présents et complets avec les 4 champs chacun, même si tu dois indiquer "Non déterminé" pour une valeur incertaine.
4. Ne jamais omettre une clé de la structure : si tu ne sais pas, écris "Non déterminé" plutôt que d'omettre le champ.

Exemple complet pour la pierre "Améthyste" (respecte EXACTEMENT ce format, adapte seulement les valeurs à "{class_name}") :

{{
  "nom": "Améthyste",
  "nom_scientifique": "Quartz améthyste",
  "categorie_geologique": "Silicate (variété de quartz)",
  "formule_chimique": "SiO2",
  "provenance_principale": "Brésil, Uruguay, Zambie",
  "physique": {{
    "durete": "7",
    "systeme_cristallin": "Trigonal",
    "clivage": "Absent",
    "densite": "2.65"
  }},
  "optique": {{
    "couleur": "Violet clair à violet foncé",
    "eclat": "Vitreux",
    "transparence": "Transparent à translucide",
    "indice_refraction": "1.544 - 1.553"
  }},
  "description": "L'améthyste est une variété violette de quartz, dont la couleur provient de traces de fer sous irradiation naturelle. Elle se forme généralement en géodes tapissées de cristaux prismatiques.",
  "histoire_symbolique": "Associée à la sobriété depuis l'Antiquité grecque, elle est aussi la pierre de naissance du mois de février."
}}

Génère maintenant le JSON pour "{class_name}", en respectant strictement cette structure et ces règles.
"""




async def ask_knowledge_agent(class_name: str) -> dict:
    """Interroge Ollama pour enrichir la classe prédite avec des données minéralogiques structurées."""
    payload = {
        "model": settings.ollama_text_model,
        "messages": [{"role": "user", "content": ANALYSIS_PROMPT_TEMPLATE.format(class_name=class_name)}],
        "stream": False,
        "format": "json",  # force Ollama à produire du JSON valide (modèles compatibles)
        # Un petit modèle respecte mieux une structure fixe en mode
        # déterministe. La diversité n'apporte rien pour ces fiches factuelles.
        "options": {"temperature": 0},
    }

    try:
        response = await app.state.http_session.post(f"{settings.ollama_url.rstrip('/')}/api/chat", json=payload)
        if response.status != 200:
            error_body = await response.text()
            logger.error(
                f"Ollama a répondu HTTP {response.status} sur /api/chat (modèle={settings.ollama_text_model}) : "
                f"{error_body[:500]}"
            )
            raise HTTPException(status_code=502, detail="L'agent de connaissance (Ollama) a répondu avec une erreur.")

        data = await response.json()
        raw_content = data.get("message", {}).get("content", "")

        # Sécurité supplémentaire au cas où le modèle ajoute des ```json ... ``` malgré la consigne
        cleaned = raw_content.strip().removeprefix("```json").removeprefix("```").removesuffix("```").strip()
        return json.loads(cleaned)

    except aiohttp.ClientConnectorError:
        raise HTTPException(status_code=503, detail="Le service de langage (Ollama) est indisponible.")
    except json.JSONDecodeError:
        logger.error(f"❌ Réponse Ollama non parsable en JSON : {raw_content[:300]}")
        raise HTTPException(status_code=502, detail="Réponse de l'agent de connaissance invalide (JSON malformé).")


@app.post("/analyze", tags=["Orchestration"], dependencies=[Depends(verify_internal_key)], response_model=StoneAnalysisResponse)
async def analyze(
    media: UploadFile | None = File(None),
    file: UploadFile | None = File(None),
):
    """
    Identification immédiate par vision (Torchvision + ViT + CLIP), validée par
    StoneAnalysisResponse. L'enrichissement Ollama est volontairement hors du
    chemin critique afin qu'une fiche lente ne bloque pas une identification.

    Accepte le champ multipart sous le nom 'media' ou 'file' : le worker
    Symfony (AnalyzeMediaMessageHandler) envoie actuellement 'file'.
    """
    upload = media or file

    if not settings.ai_enabled or not settings.vision_enabled:
        raise HTTPException(status_code=503, detail="VISION_NOT_READY")
    if not all(getattr(app.state, "readiness", {}).get(name, False) for name in ("detector", "classifier", "embeddings")):
        raise HTTPException(status_code=503, detail="DETECTOR_NOT_READY")

    if upload is not None and upload.content_type == 'video/mp4':
        try:
            contents = await upload.read()
            image = decode_video_frame(contents)
            vision_result = run_vision_pipeline(image)
        except ValueError as error:
            raise HTTPException(status_code=422, detail=str(error))
        except Exception as error:
            logger.error('Erreur durant le traitement vidéo : %s', error, exc_info=True)
            raise HTTPException(status_code=500, detail='Erreur interne de traitement vidéo.')

        return StoneAnalysisResponse(**build_vision_analysis_response(vision_result))

    if upload is None:
        raise HTTPException(status_code=422, detail="Champ fichier manquant : utilisez 'media' ou 'file'.")

    if not upload.content_type or not upload.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier fourni doit être une image.")

    try:
        contents = await upload.read()
        if len(contents) > MAX_IMAGE_BYTES:
            raise ValueError('L’image dépasse la taille maximale de 10 Mo.')
        image = Image.open(io.BytesIO(contents)).convert("RGB")
        vision_result = run_vision_pipeline(image)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except Exception as e:
        logger.error(f" Erreur durant l'inférence Vision : {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail="Erreur interne de traitement d'image.")

    try:
        return StoneAnalysisResponse(**build_vision_analysis_response(vision_result))
    except Exception as e:
        logger.error(f" Réponse de l'agent connaissance non conforme au schéma : {str(e)}")
        raise HTTPException(status_code=502, detail="L'agent de connaissance a renvoyé une structure invalide.")


@app.get("/health", tags=["Système"])
async def health_check():
    readiness = getattr(app.state, "readiness", {})
    llm_ready = False
    if settings.llm_enabled:
        session = getattr(app.state, "http_session", None)
        if session is not None:
            try:
                timeout = aiohttp.ClientTimeout(total=2)
                async with session.get(f"{settings.ollama_url.rstrip('/')}/api/tags", timeout=timeout) as response:
                    llm_ready = response.status == 200
            except (aiohttp.ClientError, asyncio.TimeoutError):
                llm_ready = False
    readiness["llm"] = llm_ready
    required = ["detector", "classifier", "embeddings"]
    if settings.llm_enabled:
        required.append("llm")
    models_ready = settings.ai_enabled and all(readiness.get(name, False) for name in required)
    return {
        "status": "healthy" if models_ready and INTERNAL_API_KEY_CONFIGURED else "degraded",
        "components": {name: bool(readiness.get(name, False)) for name in required},
        "internal_api_key_configured": INTERNAL_API_KEY_CONFIGURED,
    }
