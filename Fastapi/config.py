from pathlib import Path

# ============================
# DATASET
# ============================

DATASET_ROOT = Path("dataset")
CLASSIFICATION_ROOT = DATASET_ROOT / "classification"
METADATA_ROOT = DATASET_ROOT / "metadata"

# création automatique
CLASSIFICATION_ROOT.mkdir(parents=True, exist_ok=True)
METADATA_ROOT.mkdir(parents=True, exist_ok=True)

# ============================
# Téléchargement
# ============================

MAX_CONNECTIONS = 20

REQUEST_TIMEOUT = 30

MAX_RETRIES = 5

USER_AGENT = (
    "StoneVisionDatasetBuilder/1.0 "
    "(https://github.com/Gaetan1303)"
)

# ============================
# Images
# ============================

MIN_IMAGE_SIZE = 224

MAX_IMAGE_SIZE = 4096

SUPPORTED_EXTENSIONS = {
    ".jpg",
    ".jpeg",
    ".png",
    ".webp"
}

# ============================
# Recherche
# ============================

MINERALS = [

    "Amethyst",
    "Quartz",
    "Rose Quartz",
    "Fluorite",
    "Agate",
    "Obsidian",
    "Malachite",
    "Pyrite",
    "Calcite",
    "Jade",
    "Labradorite",
    "Moonstone",
    "Amazonite",
    "Turquoise",
    "Citrine",
    "Sodalite",
    "Howlite",
    "Carnelian",
    "Tiger Eye",
    "Lapis Lazuli"

]