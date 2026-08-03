# schema.py
from typing import Any, Optional
from pydantic import BaseModel, Field, model_validator

# ==============================================================================
# NORMALISATION — les petits modèles LLM (ex: gemma3:1b) ne respectent pas
# toujours strictement le schéma demandé dans le prompt : clés accentuées
# ("dureté" au lieu de "durete"), nombres au lieu de chaînes (3.06 au lieu de
# "3.06"), champs parfois absents. Plutôt que de faire planter la requête
# entière (502), on normalise puis on comble les champs manquants par une
# valeur explicite indiquant l'incertitude — jamais une donnée inventée.
# ==============================================================================

_NOT_DETERMINED = "Non déterminé"

_KEY_ALIASES = {
    "dureté": "durete",
    "densité": "densite",
    "système_cristallin": "systeme_cristallin",
    "système cristallin": "systeme_cristallin",
    "éclat": "eclat",
    "indice_de_refraction": "indice_refraction",
    "indice_réfraction": "indice_refraction",
}


def _normalize_keys(data: Any) -> Any:
    """Corrige récursivement les variantes de clés (accents, espaces) que le
    LLM peut renvoyer malgré les instructions du prompt."""
    if isinstance(data, dict):
        normalized = {}
        for key, value in data.items():
            clean_key = _KEY_ALIASES.get(key, key)
            normalized[clean_key] = _normalize_keys(value)
        return normalized
    if isinstance(data, list):
        return [_normalize_keys(item) for item in data]
    return data


def _coerce_to_string(value: Any) -> Any:
    """Les champs physiques/optiques sont déclarés comme str : un LLM renvoie
    parfois un nombre brut (3.06 au lieu de "3.06"). On caste plutôt que de
    rejeter — la donnée reste correcte, seul le type change."""
    if isinstance(value, (int, float)):
        return str(value)
    return value


class PhysicalProperties(BaseModel):
    durete: str = Field(..., description="Dureté sur l'échelle de Mohs")
    systeme_cristallin: str = Field(..., description="Ex: Trigonal, Cubique, Monoclinique")
    clivage: str = Field(..., description="Comportement à la fracture (ex: Parfait, Indistinct)")
    densite: str = Field(..., description="Masse volumique relative")

    @model_validator(mode="before")
    @classmethod
    def normalize(cls, data: Any) -> Any:
        if not isinstance(data, dict):
            return data
        data = _normalize_keys(data)
        for field in ("durete", "systeme_cristallin", "clivage", "densite"):
            value = _coerce_to_string(data.get(field))
            data[field] = value if value not in (None, "") else _NOT_DETERMINED
        return data


class OpticalProperties(BaseModel):
    couleur: str = Field(..., description="Couleurs principales et variantes")
    eclat: str = Field(..., description="Ex: Vitreux, Gras, Métallique")
    transparence: str = Field(..., description="Ex: Transparent, Translucide, Opaque")
    indice_refraction: Optional[str] = None

    @model_validator(mode="before")
    @classmethod
    def normalize(cls, data: Any) -> Any:
        if not isinstance(data, dict):
            return data
        data = _normalize_keys(data)
        for field in ("couleur", "eclat", "transparence"):
            value = _coerce_to_string(data.get(field))
            data[field] = value if value not in (None, "") else _NOT_DETERMINED
        if "indice_refraction" in data:
            data["indice_refraction"] = _coerce_to_string(data["indice_refraction"])
        return data


class CropAnalysis(BaseModel):
    """Vision result for one area detected by YOLO."""

    bbox: list[int] = Field(..., min_length=4, max_length=4)
    detector_confidence: float = Field(..., ge=0.0, le=1.0)
    label: str
    confidence: float = Field(..., ge=0.0, le=1.0)
    embedding: list[float] = Field(..., min_length=512, max_length=512)
    all_probabilities: dict[str, float] = Field(default_factory=dict)


class StoneAnalysisResponse(BaseModel):
    # Informations Générales
    nom: str
    nom_scientifique: Optional[str] = None
    categorie_geologique: str = _NOT_DETERMINED
    formule_chimique: str = _NOT_DETERMINED
    provenance_principale: str = Field(default=_NOT_DETERMINED, description="Où trouve-t-on principalement cette pierre ?")

    # Blocs de Propriétés Structurés
    physique: PhysicalProperties
    optique: OpticalProperties

    # Contenu textuel riche
    description: str = "Description indisponible pour cette identification."
    histoire_symbolique: Optional[str] = Field(None, description="Anecdotes historiques ou vertus associées")

    # Métadonnées de Vision (pour ton pipeline YOLO + ViT)
    confidence: float = Field(..., ge=0.0, le=1.0)
    detector_confidence: float = Field(..., ge=0.0, le=1.0)
    bbox: Optional[list[int]] = None

    # Embedding CLIP ViT-B/32 (512D, L2-normalisé), généré par run_vision_pipeline
    # dans main.py — jamais fourni par l'agent connaissance (Ollama). Persisté
    # par Symfony dans pgvector pour la recherche par similarité entre publications.
    embedding: list[float] = Field(..., min_length=512, max_length=512)

    # Full per-crop results. Root vision fields remain the primary detection
    # for backward compatibility with the Symfony workers.
    detections: list[CropAnalysis] = Field(default_factory=list)
    model_version: dict[str, str] = Field(default_factory=dict)

    @model_validator(mode="before")
    @classmethod
    def normalize_top_level(cls, data: Any) -> Any:
        if not isinstance(data, dict):
            return data
        data = _normalize_keys(data)
        # Champs texte requis avec défaut explicite si le LLM les a omis —
        # jamais de valeur inventée, juste un marqueur "non déterminé".
        for field in ("categorie_geologique", "formule_chimique", "provenance_principale"):
            value = data.get(field)
            if value in (None, ""):
                data[field] = _NOT_DETERMINED
        if data.get("description") in (None, ""):
            data["description"] = "Description indisponible pour cette identification."
        # Les blocs 'physique'/'optique' sont parfois omis en entier par le
        # LLM (pas juste un sous-champ) : on leur substitue un dict vide plutôt
        # qu'un champ manquant, pour que les validateurs imbriqués de
        # PhysicalProperties/OpticalProperties prennent le relais et comblent
        # chaque sous-champ avec _NOT_DETERMINED au lieu de faire échouer
        # toute la réponse.
        if not isinstance(data.get("physique"), dict):
            data["physique"] = {}
        if not isinstance(data.get("optique"), dict):
            data["optique"] = {}
        return data
