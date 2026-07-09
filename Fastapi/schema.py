# schema.py
from typing import List, Optional
from pydantic import BaseModel, Field

class PhysicalProperties(BaseModel):
    durete: str = Field(..., description="Dureté sur l'échelle de Mohs")
    systeme_cristallin: str = Field(..., description="Ex: Trigonal, Cubique, Monoclinique")
    clivage: str = Field(..., description="Comportement à la fracture (ex: Parfait, Indistinct)")
    densite: str = Field(..., description="Masse volumique relative")

class OpticalProperties(BaseModel):
    couleur: str = Field(..., description="Couleurs principales et variantes")
    eclat: str = Field(..., description="Ex: Vitreux, Gras, Métallique")
    transparence: str = Field(..., description="Ex: Transparent, Translucide, Opaque")
    indice_refraction: Optional[str] = None

class StoneAnalysisResponse(BaseModel):
    # Informations Générales
    nom: str
    nom_scientifique: Optional[str] = None
    categorie_geologique: str
    formule_chimique: str
    provenance_principale: str = Field(..., description="Où trouve-t-on principalement cette pierre ?")
    
    # Blocs de Propriétés Structurés
    physique: PhysicalProperties
    optique: OpticalProperties
    
    # Contenu textuel riche
    description: str
    histoire_symbolique: Optional[str] = Field(None, description="Anecdotes historiques ou vertus associées")
    
    # Métadonnées de Vision (pour ton pipeline YOLO + ViT)
    confidence: float
    detector_confidence: float
    bbox: Optional[list[int]] = None