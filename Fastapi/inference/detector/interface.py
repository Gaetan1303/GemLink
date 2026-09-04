from abc import ABC, abstractmethod
from dataclasses import dataclass

from PIL import Image


class DetectorNotReadyError(RuntimeError):
    code = "DETECTOR_NOT_READY"


@dataclass(frozen=True)
class Detection:
    bbox: list[float]
    confidence: float


class DetectorInterface(ABC):
    @abstractmethod
    def detect(self, image: Image.Image, confidence_threshold: float) -> list[Detection]:
        """Return all detections whose confidence is above the threshold."""
