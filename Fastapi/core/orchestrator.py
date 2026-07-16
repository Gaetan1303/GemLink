# core/orchestrator.py
import torch
from PIL import Image
from typing import Any, Dict

from schema import StoneAnalysisResponse, PhysicalProperties, OpticalProperties
from database import MINERAL_DB  # Import unique de MINERAL_DB pour éviter l'ImportError
from inference.classifier.predict_engine import predict
from utils import crop_with_padding
from engines.remote_vision import analyze_with_llm_vision

# Fiche d'identité géologique par défaut utilisée comme plan de secours textuel
DEFAULT_STONE = {
    "nom": "Minéral Inconnu",
    "nom_scientifique": "Unknown",
    "categorie_geologique": "Inconnue",
    "formule_chimique": "N/A",
    "provenance_principale": "Inconnue",
    "physique": {
        "durete": "N/A",
        "densite": "N/A",
        "systeme_cristallin": "N/A",
        "clivage": "N/A",
        "cassure": "N/A"
    },
    "optique": {
        "couleur": "Inconnue",
        "eclat": "Inconnu",
        "transparence": "Inconnue",
        "indice_refraction": "N/A"
    },
    "description": "Ce minéral a été détecté par l'IA locale mais sa fiche n'est pas encore répertoriée dans notre encyclopédie.",
    "histoire_symbolique": "Aucune information symbolique disponible."
}

# Seuil de confiance en dessous duquel on sollicite le LLM de Vision en secours
VIT_CONFIDENCE_THRESHOLD = 0.75


class AIOrchestrator:
    def __init__(self, app_state: Any):
        """
        Récupère dynamiquement les instances de modèles chargées lors du lifespan FastAPI.
        """
        self.detector = getattr(app_state, "detector", None)
        self.classifier_model = getattr(app_state, "classifier_model", None)
        self.classes = getattr(app_state, "classes", None)
        self.preprocess = getattr(app_state, "preprocess", None)
        self.device = "cuda" if torch.cuda.is_available() else "cpu"

    async def route_and_analyze(self, image: Image.Image, image_bytes: bytes) -> StoneAnalysisResponse:
        """
        Point d'entrée principal arbitrant entre la puissance locale et la précision distante.
        """
        # 1. Tentative d'exécution via le pipeline local (YOLO + ViT)
        local_result = await self._run_local_pipeline(image)
        
        # 2. Vérification du critère de réussite locale
        if local_result["success"] and local_result["confidence"] >= VIT_CONFIDENCE_THRESHOLD:
            print(f"🎯 [ORCHESTRATOR] Succès local ({local_result['confidence']:.2f}). Enrichissement via DB.")
            return self._build_response_from_local_db(local_result)
        
        # 3. Fallback : Routage vers l'IA de Vision externe ou Ollama local
        print("🌐 [ORCHESTRATOR] Fallback activé. Confiance locale insuffisante ou échec détecteur.")
        return await _invoke_remote_engine(image_bytes)

    async def _run_local_pipeline(self, image: Image.Image) -> Dict[str, Any]:
        """
        Exécute la chaîne locale classique : Détection -> Crop -> Classification.
        """
        # Garde-fou si les poids locaux du classifieur ne sont pas chargés ou indisponibles
        if not self.classifier_model or not self.preprocess:
            return {"success": False, "confidence": 0.0}

        try:
            # Étape A : Détection YOLO
            bbox = None
            det_conf = 0.0
            if self.detector:
                try:
                    bbox, det_conf = self.detector.detect(image)
                except Exception as e:
                    print(f"⚠️ Défaillance technique du détecteur YOLO : {e}")

            # Si YOLO échoue à localiser un minéral, on invalide la certitude locale pour déclencher le fallback
            if bbox is None:
                return {"success": False, "confidence": 0.0}

            # Étape B : Extraction de la zone d'intérêt (Crop)
            crop = crop_with_padding(image, bbox, padding_ratio=0.08)

            # Étape C : Inférence par le Vision Transformer
            image_tensor = self.preprocess(crop).unsqueeze(0)
            result = predict(
                model=self.classifier_model,
                image_tensor=image_tensor,
                classes=self.classes,
                device=self.device,
                threshold=0.1,
                top_k=5
            )

            return {
                "success": True,
                "label": result["label"].lower(),
                "confidence": result["confidence"],
                "detector_confidence": det_conf,
                "bbox": bbox,
                "top_k": result["top_k"]
            }

        except Exception as e:
            print(f"💥 Erreur critique durant l'inférence locale : {e}")
            return {"success": False, "confidence": 0.0}

    def _build_response_from_local_db(self, local_result: Dict[str, Any]) -> StoneAnalysisResponse:
        """
        Mappe le label de l'IA locale avec les métadonnées de l'encyclopédie database.py.
        """
        label = local_result["label"]
        geo_data = MINERAL_DB.get(label, {**DEFAULT_STONE, "nom": label.capitalize()})
        
        return StoneAnalysisResponse(
            nom=geo_data["nom"],
            nom_scientifique=geo_data.get("nom_scientifique"),
            categorie_geologique=geo_data["categorie_geologique"],
            formule_chimique=geo_data["formule_chimique"],
            provenance_principale=geo_data.get("provenance_principale", "Inconnue"),
            physique=PhysicalProperties(**geo_data["physique"]),
            optique=OpticalProperties(**geo_data["optique"]),
            description=geo_data["description"],
            histoire_symbolique=geo_data.get("histoire_symbolique"),
            confidence=local_result["confidence"],
            detector_confidence=local_result["detector_confidence"],
            bbox=local_result["bbox"]
        )


async def _invoke_remote_engine(image_bytes: bytes) -> StoneAnalysisResponse:
    """
    Isole l'appel réseau vers Ollama/OpenAI pour préserver la réactivité de l'orchestrateur.
    """
    try:
        return await analyze_with_llm_vision(image_bytes)
    except Exception as e:
        print(f"🚨 Échec absolu du moteur distant : {e}")
        raise e