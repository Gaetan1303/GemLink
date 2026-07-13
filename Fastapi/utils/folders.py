from pathlib import Path

from config import CLASSIFICATION_ROOT


def get_class_folder(mineral: str) -> Path:

    folder = CLASSIFICATION_ROOT / mineral

    folder.mkdir(parents=True, exist_ok=True)

    return folder