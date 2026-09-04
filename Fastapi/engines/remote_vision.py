# engines/remote_vision.py
import httpx
from fastapi import HTTPException
from app.config import get_settings
from schema import StoneAnalysisResponse

settings = get_settings()

async def analyze_with_llm_vision(image_bytes: bytes) -> StoneAnalysisResponse:
    """
    Envoie l'image au modèle de vision open-source hébergé localement sur Ollama.
    """
    import base64
    base64_image = base64.b64encode(image_bytes).decode("utf-8")

    # Prompt ultra-directif pour forcer l'extraction
    prompt = (
        "Analyze this mineral image. Return a strict JSON object following this exact schema: "
        f"{StoneAnalysisResponse.model_json_schema()}"
    )

    payload = {
        "model": settings.ollama_vision_model,
        "messages": [
            {
                "role": "user",
                "content": prompt,
                "images": [base64_image]
            }
        ],
        "format": "json",  # Force Ollama à répondre en JSON valide
        "stream": False,
        "options": {
            "temperature": 0.2
        }
    }

    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{settings.ollama_url.rstrip('/')}/api/chat",
                json=payload,
                timeout=60.0 # On laisse du temps au CPU pour calculer l'inférence LLM
            )

        if response.status_code != 200:
            raise HTTPException(status_code=502, detail="Ollama a renvoyé une erreur.")

        response_data = response.json()
        raw_json_string = response_data["message"]["content"]

        # Validation Pydantic pour blinder le contrat de données
        validated_response = StoneAnalysisResponse.model_validate_json(raw_json_string)
        
        # Hydratation des métadonnées de secours
        validated_response.confidence = 0.90
        validated_response.detector_confidence = 0.0
        return validated_response

    except Exception as e:
        print(f"🚨 Échec de l'inférence Ollama : {e}")
        raise HTTPException(status_code=500, detail=f"Erreur d'inférence locale LLM : {str(e)}")
