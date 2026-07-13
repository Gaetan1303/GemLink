import io
import os
import json
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, File, UploadFile, HTTPException, Request, Header, Depends
from fastapi.responses import JSONResponse, StreamingResponse
import torch
from torchvision import transforms
from PIL import Image
import aiohttp

# Import de votre architecture, du détecteur et des utilitaires
from vit import get_model
from ultralytics import YOLO
from utils import crop_with_padding
from schema import StoneAnalysisResponse

# Configuration des logs
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger("GemLinkAI")

# ==============================================================================
# VARIABLES D'ENVIRONNEMENT
# ==============================================================================
YOLO_MODEL_PATH = os.getenv(
    "YOLO_MODEL_PATH",
    "runs/detect/runs/stone_detector/weights/best.pt"  # corrigé : évite le doublon "runs/detect/runs/..."
)
VIT_MODEL_PATH = os.getenv("VIT_MODEL_PATH", "checkpoints/vit_stones.pth")
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://ollama:11434")
OLLAMA_TEXT_MODEL = os.getenv("OLLAMA_TEXT_MODEL", "llama3.1")

# Clé partagée avec Symfony : ce service ne doit JAMAIS être appelable
# directement par le frontend Angular, uniquement par le backend Symfony.
INTERNAL_API_KEY = os.getenv("INTERNAL_API_KEY")
if not INTERNAL_API_KEY:
    logger.warning(" INTERNAL_API_KEY non définie : les endpoints /predict, /analyze et /api/chat "
                    "sont accessibles sans authentification. À ne jamais faire en production.")


async def verify_internal_key(x_internal_key: str = Header(None)):
    """Dépendance FastAPI : vérifie que l'appel vient bien de Symfony (ou d'un service autorisé)."""
    if INTERNAL_API_KEY and x_internal_key != INTERNAL_API_KEY:
        raise HTTPException(status_code=403, detail="Accès interdit : clé interne manquante ou invalide.")


# ==============================================================================
# GESTION DU CYCLE DE VIE DES MODÈLES (LIFESPAN)
# ==============================================================================
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Initialisation des modèles Vision au démarrage de l'orchestrateur."""
    logger.info(" Démarrage de GemLink AI - Initialisation de la pipeline globale...")
    device = "cuda" if torch.cuda.is_available() else "cpu"
    logger.info(f" Exécution configurée sur le matériel : {device.upper()}")

    app.state.device = device
    app.state.http_session = aiohttp.ClientSession()

    try:
        # 1. Chargement du checkpoint ViT (format {"model": state_dict, "classes": [...]})
        logger.info(f" Chargement des checkpoints depuis {VIT_MODEL_PATH}...")
        checkpoint = torch.load(VIT_MODEL_PATH, map_location=device)
        app.state.classes = checkpoint["classes"]
        logger.info(f" Classes détectées : {len(app.state.classes)} classes")

        # 2. Reconstruction de l'architecture ViT
        logger.info(" Reconstruction de l'architecture Vision Transformer (ViT)...")
        app.state.classifier_model = get_model(num_classes=len(app.state.classes))
        app.state.classifier_model.load_state_dict(checkpoint["model"])
        app.state.classifier_model.to(device)
        app.state.classifier_model.eval()

        # 3. Chargement du détecteur YOLOv8
        logger.info(f" Chargement du détecteur YOLOv8 depuis {YOLO_MODEL_PATH}...")
        app.state.detector = YOLO(YOLO_MODEL_PATH)
        app.state.detector.to(device)

        # 4. Prétraitement des images (DOIT rester identique aux transforms utilisés à l'entraînement)
        app.state.preprocess = transforms.Compose([
            transforms.Resize((224, 224)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])

        logger.info(" Pipeline Vision et Client HTTP initialisés avec succès.")

    except Exception as e:
        logger.critical(f" Échec critique lors de l'initialisation de l'IA : {str(e)}")
        raise e

    yield
    # Nettoyage à l'arrêt du conteneur
    logger.info(" Fermeture de GemLink AI - Libération des ressources...")
    await app.state.http_session.close()
    if hasattr(app.state, "classifier_model"):
        del app.state.classifier_model
    if hasattr(app.state, "detector"):
        del app.state.detector


app = FastAPI(title="GemLink AI Orchestrator API", version="1.1.0", lifespan=lifespan)


# ==============================================================================
# SECTION ROUTAGE : CHAT / LLM (OLLAMA PROXY)
# ==============================================================================

@app.post("/api/chat", tags=["LLM Chat"], dependencies=[Depends(verify_internal_key)])
async def chat_proxy(request: Request):
    """Transfère les requêtes de chat à Ollama en préservant le streaming."""
    try:
        body = await request.json()

        ollama_response = await app.state.http_session.post(
            f"{OLLAMA_URL}/api/chat",
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
# SECTION ROUTAGE : PIPELINE VISION (YOLO + ViT)
# ==============================================================================

def run_vision_pipeline(image: Image.Image) -> dict:
    """
    Exécute la détection YOLO puis la classification ViT sur une image.
    Réutilisée par /predict (rapide, agent vision seul) et /analyze (agent vision + agent connaissance).
    Lève ValueError si aucun minéral n'est détecté.
    """
    yolo_results = app.state.detector(image, verbose=False)
    if len(yolo_results) == 0 or len(yolo_results[0].boxes) == 0:
        raise ValueError("Aucun minéral localisé dans l'image.")

    best_box = yolo_results[0].boxes[0]
    bbox = best_box.xyxy[0].cpu().numpy().tolist()
    det_conf = float(best_box.conf[0].cpu().item())

    # Crop avec marge pour ne pas couper un bord de la pierre (bbox YOLO souvent trop serrée)
    crop_img = crop_with_padding(image, bbox)

    input_tensor = app.state.preprocess(crop_img).unsqueeze(0).to(app.state.device)

    with torch.no_grad():
        outputs = app.state.classifier_model(input_tensor)
        probabilities = torch.nn.functional.softmax(outputs[0], dim=0)
        top_prob, top_idx = torch.max(probabilities, dim=0)

        class_name = app.state.classes[top_idx.item()]
        class_conf = float(top_prob.item())

    return {
        "bbox": [int(v) for v in bbox],
        "detector_confidence": det_conf,
        "predicted_class": class_name,
        "confidence": class_conf,
        "all_probabilities": {
            app.state.classes[i]: float(probabilities[i].item())
            for i in range(len(app.state.classes))
        },
    }


@app.post("/predict", tags=["Vision Pipeline"], dependencies=[Depends(verify_internal_key)])
async def predict(file: UploadFile = File(...)):
    """Agent vision seul : détection + classification, sans enrichissement LLM. Rapide."""
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier fourni doit être une image.")

    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents)).convert("RGB")
        result = run_vision_pipeline(image)

        return {
            "success": True,
            "detection": {"box": result["bbox"], "confidence": result["detector_confidence"]},
            "classification": {
                "predicted_class": result["predicted_class"],
                "confidence": result["confidence"],
                "all_probabilities": result["all_probabilities"],
            }
        }

    except ValueError as e:
        return JSONResponse(status_code=200, content={"success": False, "message": str(e), "detections": []})
    except Exception as e:
        logger.error(f" Erreur durant l'inférence Vision : {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail="Erreur interne de traitement d'image.")


# ==============================================================================
# SECTION ROUTAGE : ORCHESTRATION COMPLÈTE (AGENT VISION + AGENT CONNAISSANCE)
# ==============================================================================

ANALYSIS_PROMPT_TEMPLATE = """Tu es un minéralogiste expert. On te donne le nom d'une pierre : "{class_name}".
Réponds UNIQUEMENT avec un objet JSON valide (aucun texte avant/après, aucun bloc markdown),
respectant EXACTEMENT cette structure :

{{
  "nom": "string",
  "nom_scientifique": "string ou null",
  "categorie_geologique": "string",
  "formule_chimique": "string",
  "provenance_principale": "string",
  "physique": {{
    "durete": "string",
    "systeme_cristallin": "string",
    "clivage": "string",
    "densite": "string"
  }},
  "optique": {{
    "couleur": "string",
    "eclat": "string",
    "transparence": "string",
    "indice_refraction": "string ou null"
  }},
  "description": "string (3-4 phrases)",
  "histoire_symbolique": "string ou null"
}}
"""


async def ask_knowledge_agent(class_name: str) -> dict:
    """Interroge Ollama pour enrichir la classe prédite avec des données minéralogiques structurées."""
    payload = {
        "model": OLLAMA_TEXT_MODEL,
        "messages": [{"role": "user", "content": ANALYSIS_PROMPT_TEMPLATE.format(class_name=class_name)}],
        "stream": False,
        "format": "json",  # force Ollama à produire du JSON valide (modèles compatibles)
    }

    try:
        response = await app.state.http_session.post(f"{OLLAMA_URL}/api/chat", json=payload)
        if response.status != 200:
            raise HTTPException(status_code=502, detail="L'agent de connaissance (Ollama) a répondu avec une erreur.")

        data = await response.json()
        raw_content = data.get("message", {}).get("content", "")

        # Sécurité supplémentaire au cas où le modèle ajoute des ```json ... ``` malgré la consigne
        cleaned = raw_content.strip().removeprefix("```json").removeprefix("```").removesuffix("```").strip()
        return json.loads(cleaned)

    except aiohttp.ClientConnectorError:
        raise HTTPException(status_code=503, detail="Le service de langage (Ollama) est indisponible.")
    except json.JSONDecodeError:
        logger.error(f" Réponse Ollama non parsable en JSON : {raw_content[:300]}")
        raise HTTPException(status_code=502, detail="Réponse de l'agent de connaissance invalide (JSON malformé).")


@app.post("/analyze", tags=["Orchestration"], dependencies=[Depends(verify_internal_key)], response_model=StoneAnalysisResponse)
async def analyze(file: UploadFile = File(...)):
    """
    Pipeline complet : Agent Vision (YOLO + ViT) -> Agent Connaissance (Ollama)
    -> validation stricte via StoneAnalysisResponse.
    C'est l'endpoint que Symfony doit appeler (POST /analyze dans votre diagramme).
    """
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier fourni doit être une image.")

    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents)).convert("RGB")
        vision_result = run_vision_pipeline(image)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except Exception as e:
        logger.error(f" Erreur durant l'inférence Vision : {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail="Erreur interne de traitement d'image.")

    knowledge_data = await ask_knowledge_agent(vision_result["predicted_class"])

    # Fusion : les champs de vision priment toujours sur ce que l'agent connaissance pourrait halluciner
    knowledge_data["confidence"] = vision_result["confidence"]
    knowledge_data["detector_confidence"] = vision_result["detector_confidence"]
    knowledge_data["bbox"] = vision_result["bbox"]

    try:
        return StoneAnalysisResponse(**knowledge_data)
    except Exception as e:
        logger.error(f" Réponse de l'agent connaissance non conforme au schéma : {str(e)}")
        raise HTTPException(status_code=502, detail="L'agent de connaissance a renvoyé une structure invalide.")


@app.get("/health", tags=["Système"])
async def health_check():
    models_ready = hasattr(app.state, "detector") and hasattr(app.state, "classifier_model")
    return {
        "status": "healthy" if models_ready else "degraded",
        "device": app.state.device if hasattr(app.state, "device") else "unknown",
        "ollama_connectivity": not app.state.http_session.closed
    }